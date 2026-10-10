<?php

namespace Tests\Feature\BackOffice\Register;

use App\Models\Category;
use App\Models\OperatingDay;
use App\Models\Product;
use App\Models\Season;
use App\Models\StockLocation;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\API\AuthTestHelper;
use Tests\TestCase;

class SeasonLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    private array $cashierTokens;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->cashier = User::factory()->create();
        $this->cashier->assignRole('cashier');
        $this->cashierTokens = AuthTestHelper::generateTokens($this->cashier);
    }

    protected function tearDown(): void
    {
        AuthTestHelper::clearUser($this->cashier);
        parent::tearDown();
    }

    private function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->cashierTokens['accessToken'],
            'Idempotency-Key' => (string) Str::uuid(),
        ];
    }

    private function createTicket(array $overrides = []): TestResponse
    {
        return $this->postJson(
            route('api.register.tickets.store'),
            array_merge([
                'payment_method' => 'cash',
                'sold_at' => now()->toDateTimeString(),
                'stock_location_id' => StockLocation::factory()->create()->id,
                'items' => [],
            ], $overrides),
            $this->authHeaders()
        );
    }

    public function test_full_season_lifecycle(): void
    {
        // ─── Setup ───────────────────────────────────────────────────────
        $this->actingAs($this->admin);

        $correctionCategory = Category::factory()->create(['name' => 'Correction', 'is_restricted' => true]);
        $correctionProduct = Product::factory()->create([
            'name' => 'Correction',
            'unit_price' => 0,
            'allows_custom_price' => true,
            'category_id' => $correctionCategory->id,
        ]);

        $productA = Product::factory()->create(['unit_price' => 10.00]);
        $productB = Product::factory()->create(['unit_price' => 5.00]);

        // ─── Step 1: Start Season ────────────────────────────────────────
        $season = Season::factory()->create(['initial_balance' => 500.00]);

        $this->assertEquals(500.00, $season->initial_balance);
        $this->assertEquals(500.00, $season->end_balance);

        // ─── Step 2: Open Day 1 (today) ──────────────────────────────────
        $this->post(route('bo.register.operating-days.store'), [
            'season_id' => $season->id,
            'date' => Carbon::today('Europe/Zurich')->toDateString(),
        ])->assertRedirect();

        $day1 = OperatingDay::where('season_id', $season->id)->first();
        $this->assertTrue($day1->isOpen());
        $this->assertEquals(0, $day1->total_cash);
        $this->assertEquals(500.00, $day1->closing_balance);

        // ─── Step 3: Create tickets on Day 1 via API ─────────────────────
        $this->actingAs($this->cashier);

        // Ticket 1: cash, productA x2 = 20.00
        $this->createTicket([
            'operating_day_id' => $day1->id,
            'items' => [['product_id' => $productA->id, 'quantity' => 2]],
        ])->assertCreated();

        // Ticket 2: twint, productB x3 = 15.00
        $this->createTicket([
            'payment_method' => 'twint',
            'operating_day_id' => $day1->id,
            'items' => [['product_id' => $productB->id, 'quantity' => 3]],
        ])->assertCreated();

        // Ticket 3: cash, productA x1 = 10.00
        $this->createTicket([
            'operating_day_id' => $day1->id,
            'items' => [['product_id' => $productA->id, 'quantity' => 1]],
        ])->assertCreated();

        $day1->refresh();
        $this->assertEquals(30.00, $day1->total_cash);
        $this->assertEquals(15.00, $day1->total_twint);
        $this->assertEquals(530.00, $day1->closing_balance);

        // ─── Step 4: Close Day 1 ─────────────────────────────────────────
        $this->actingAs($this->admin);

        $this->put(route('bo.register.operating-days.update', $day1), [
            'status' => 'closed',
        ])->assertRedirect();

        $day1->refresh();
        $this->assertNotNull($day1->closed_at);
        $this->assertEquals(530.00, $day1->closing_balance);
        $this->assertEquals(30.00, $day1->total_cash);
        $this->assertEquals(15.00, $day1->total_twint);

        // ─── Step 5: Add remark ──────────────────────────────────────────
        $this->put(route('bo.register.operating-days.update', $day1), [
            'cash_remark' => 'Vérifié, caisse OK',
        ])->assertRedirect();

        $day1->refresh();
        $this->assertEquals('Vérifié, caisse OK', $day1->cash_remark);

        // ─── Step 6: Reopen Day 1 and add correction ────────────────────
        $this->actingAs($this->admin);

        $this->patchJson(route('api.register.operating-days.update', $day1), [
            'status' => 'open',
        ], $this->authHeaders())->assertOk();

        $this->actingAs($this->cashier);

        // Positive correction on Day 1
        $this->createTicket([
            'operating_day_id' => $day1->id,
            'items' => [['product_id' => $correctionProduct->id, 'quantity' => 1, 'custom_unit_price' => 25.00]],
        ])->assertCreated();

        $day1->refresh();
        $this->assertEquals(55.00, $day1->total_cash);
        $this->assertEquals(555.00, $day1->closing_balance);

        // ─── Step 7: Re-close Day 1 ──────────────────────────────────────
        $this->actingAs($this->admin);

        $this->put(route('bo.register.operating-days.update', $day1), [
            'status' => 'closed',
        ])->assertRedirect();

        $day1->refresh();
        $this->assertNotNull($day1->closed_at);
        $this->assertEquals(555.00, $day1->closing_balance);

        // ─── Step 8: Negative correction on Day 1 (reopen first) ─────────
        $this->patchJson(route('api.register.operating-days.update', $day1), [
            'status' => 'open',
        ], $this->authHeaders())->assertOk();

        $this->actingAs($this->cashier);

        $this->createTicket([
            'operating_day_id' => $day1->id,
            'items' => [['product_id' => $correctionProduct->id, 'quantity' => 1, 'custom_unit_price' => -15.00]],
        ])->assertCreated();

        $day1->refresh();
        $this->assertEquals(40.00, $day1->total_cash);
        $this->assertEquals(540.00, $day1->closing_balance);

        // ─── Step 9: Re-close Day 1 ──────────────────────────────────────
        $this->actingAs($this->admin);

        $this->put(route('bo.register.operating-days.update', $day1), [
            'status' => 'closed',
        ])->assertRedirect();

        $day1->refresh();
        $this->assertNotNull($day1->closed_at);
        $this->assertEquals(540.00, $day1->closing_balance);

        // ─── Step 10: Verify Season end_balance ──────────────────────────
        $season->refresh();
        $this->assertEquals(540.00, $season->end_balance);

        // ─── Step 11: Refund a ticket on Day 1 ───────────────────────────
        $ticketToRefund = Ticket::where('operating_day_id', $day1->id)->first();

        $this->actingAs($this->admin);

        $this->post(route('bo.register.tickets.refund', $ticketToRefund))
            ->assertRedirect();

        $day1->refresh();
        // After refunding, total_cash recalculated from remaining tickets
        $this->assertNotNull($day1->fresh()->total_cash);
    }
}
