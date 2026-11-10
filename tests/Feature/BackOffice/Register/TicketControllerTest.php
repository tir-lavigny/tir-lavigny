<?php

namespace Tests\Feature\BackOffice\Register;

use App\Models\Category;
use App\Models\OperatingDay;
use App\Models\Product;
use App\Models\Season;
use App\Models\StockLocation;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    }

    private function createCorrectionProduct(): Product
    {
        $category = Category::factory()->create(['name' => 'Correction', 'is_restricted' => true]);

        return Product::factory()->create([
            'name' => 'Correction',
            'unit_price' => 0,
            'allows_custom_price' => true,
            'is_stockable' => false,
            'category_id' => $category->id,
        ]);
    }

    private function createClosedDay(Season $season, array $attributes = []): OperatingDay
    {
        return OperatingDay::factory()->closed()->create(array_merge([
            'season_id' => $season->id,
            'date' => Carbon::parse('2026-05-01'),
            'total_cash' => 0,
            'total_twint' => 0,
            'closing_balance' => (float) $season->initial_balance,
        ], $attributes));
    }

    public function test_refund_soft_deletes_ticket(): void
    {
        $season = Season::factory()->create();
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        $ticket = Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'total' => 25.00,
        ]);

        $response = $this->post(route('bo.register.tickets.refund', $ticket));

        $response->assertRedirect();
        $this->assertSoftDeleted('tickets', ['id' => $ticket->id]);
    }

    public function test_refund_recalculates_day_totals(): void
    {
        $season = Season::factory()->create();
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'total_cash' => 0,
            'total_twint' => 0,
        ]);
        Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'total' => 50.00,
        ]);

        $day->recalculateTotals();
        $this->assertEquals('50.00', $day->fresh()->total_cash);

        $ticket = Ticket::where('operating_day_id', $day->id)->first();
        $this->post(route('bo.register.tickets.refund', $ticket));

        $day->refresh();
        $this->assertEquals('0.00', $day->total_cash);
    }

    public function test_refund_requires_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view register');
        $this->actingAs($user);

        $season = Season::factory()->create();
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        $ticket = Ticket::factory()->cash()->create(['operating_day_id' => $day->id]);

        $response = $this->post(route('bo.register.tickets.refund', $ticket));

        $response->assertForbidden();
    }

    public function test_correction_creates_cash_ticket_on_closed_day(): void
    {
        $correction = $this->createCorrectionProduct();
        StockLocation::factory()->create(['active' => true]);
        $season = Season::factory()->create(['initial_balance' => 100]);
        $day = $this->createClosedDay($season, ['total_cash' => 20]);
        Ticket::factory()->cash()->create(['operating_day_id' => $day->id, 'total' => 20]);

        $response = $this->post(route('bo.register.operating-days.corrections.store', $day), [
            'amount' => 25,
        ]);

        $response->assertRedirect(route('bo.register.operating-days.show', $day));
        $response->assertSessionHas('success');

        $ticket = Ticket::where('operating_day_id', $day->id)->where('total', 25.00)->firstOrFail();
        $this->assertEquals('25.00', $ticket->total);
        $this->assertEquals('cash', $ticket->payment_method);
        $this->assertNotNull($day->fresh()->closed_at);
        $this->assertEquals('45.00', $day->fresh()->total_cash);

        $item = TicketItem::where('ticket_id', $ticket->id)->firstOrFail();
        $this->assertEquals($correction->id, $item->product_id);
        $this->assertEquals(1, $item->quantity);
        $this->assertEquals('25.00', $item->unit_price);
    }

    public function test_correction_negative_amount_reduces_day_cash_and_closing_balance(): void
    {
        $this->createCorrectionProduct();
        StockLocation::factory()->create(['active' => true]);
        $season = Season::factory()->create(['initial_balance' => 1000]);
        $day = $this->createClosedDay($season, [
            'total_cash' => 200,
            'closing_balance' => 1200,
        ]);
        Ticket::factory()->cash()->create(['operating_day_id' => $day->id, 'total' => 200]);

        $this->post(route('bo.register.operating-days.corrections.store', $day), [
            'amount' => -15,
        ])->assertRedirect();

        $this->assertEquals('185.00', $day->fresh()->total_cash);
        $this->assertEquals(1185.00, $day->fresh()->closing_balance);
        $this->assertEquals(1185.00, $season->fresh()->end_balance);
    }

    public function test_correction_does_not_touch_twint_total(): void
    {
        $this->createCorrectionProduct();
        StockLocation::factory()->create(['active' => true]);
        $season = Season::factory()->create();
        $day = $this->createClosedDay($season, ['total_cash' => 0, 'total_twint' => 80]);
        Ticket::factory()->twint()->create(['operating_day_id' => $day->id, 'total' => 80]);

        $this->post(route('bo.register.operating-days.corrections.store', $day), [
            'amount' => 30,
        ])->assertRedirect();

        $day->refresh();
        $this->assertEquals('30.00', $day->total_cash);
        $this->assertEquals('80.00', $day->total_twint);
    }

    public function test_correction_cascades_closing_balances_to_later_closed_days(): void
    {
        $this->createCorrectionProduct();
        StockLocation::factory()->create(['active' => true]);
        $season = Season::factory()->create(['initial_balance' => 1000]);
        $day1 = $this->createClosedDay($season, [
            'date' => Carbon::parse('2026-05-01'),
            'total_cash' => 100,
            'closing_balance' => 1100,
        ]);
        $day2 = $this->createClosedDay($season, [
            'date' => Carbon::parse('2026-05-02'),
            'total_cash' => 50,
            'closing_balance' => 1150,
        ]);
        Ticket::factory()->cash()->create(['operating_day_id' => $day1->id, 'total' => 100]);
        Ticket::factory()->cash()->create(['operating_day_id' => $day2->id, 'total' => 50]);

        $this->post(route('bo.register.operating-days.corrections.store', $day1), [
            'amount' => -40,
        ])->assertRedirect();

        $this->assertEquals(1060.00, $day1->fresh()->closing_balance);
        $this->assertEquals(1110.00, $day2->fresh()->closing_balance);
        $this->assertEquals(1110.00, $season->fresh()->end_balance);
    }

    public function test_correction_works_on_open_day(): void
    {
        $this->createCorrectionProduct();
        StockLocation::factory()->create(['active' => true]);
        $season = Season::factory()->create();
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'closed_at' => null,
            'total_cash' => 0,
        ]);

        $this->post(route('bo.register.operating-days.corrections.store', $day), [
            'amount' => 12.50,
        ])->assertRedirect();

        $day->refresh();
        $this->assertNull($day->closed_at);
        $this->assertEquals('12.50', $day->total_cash);
    }

    public function test_correction_uses_last_ticket_stock_location(): void
    {
        $this->createCorrectionProduct();
        $fallbackLocation = StockLocation::factory()->create(['active' => true]);
        $soldLocation = StockLocation::factory()->create(['active' => true]);
        $season = Season::factory()->create();
        $day = $this->createClosedDay($season);
        $soldTicket = Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'stock_location_id' => $soldLocation->id,
            'sold_at' => Carbon::parse('2026-05-01 12:00:00'),
        ]);

        $this->post(route('bo.register.operating-days.corrections.store', $day), [
            'amount' => 5,
        ])->assertRedirect();

        $correctionTicket = Ticket::where('operating_day_id', $day->id)
            ->whereKeyNot($soldTicket->id)
            ->firstOrFail();

        $this->assertEquals($soldLocation->id, $correctionTicket->stock_location_id);
        $this->assertNotEquals($fallbackLocation->id, $correctionTicket->stock_location_id);
    }

    public function test_correction_fails_without_correction_product(): void
    {
        StockLocation::factory()->create(['active' => true]);
        $season = Season::factory()->create();
        $day = $this->createClosedDay($season);

        $response = $this->post(route('bo.register.operating-days.corrections.store', $day), [
            'amount' => 10,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_correction_fails_without_active_stock_location(): void
    {
        $this->createCorrectionProduct();
        $season = Season::factory()->create();
        $day = $this->createClosedDay($season);

        $response = $this->post(route('bo.register.operating-days.corrections.store', $day), [
            'amount' => 10,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_correction_rejects_missing_and_zero_amount(): void
    {
        $this->createCorrectionProduct();
        StockLocation::factory()->create(['active' => true]);
        $season = Season::factory()->create();
        $day = $this->createClosedDay($season);

        $this->post(route('bo.register.operating-days.corrections.store', $day), [])
            ->assertSessionHasErrors(['amount']);
        $this->post(route('bo.register.operating-days.corrections.store', $day), ['amount' => 0])
            ->assertSessionHasErrors(['amount']);
        $this->post(route('bo.register.operating-days.corrections.store', $day), ['amount' => 'abc'])
            ->assertSessionHasErrors(['amount']);

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_correction_requires_create_ticket_permission(): void
    {
        $this->createCorrectionProduct();
        StockLocation::factory()->create(['active' => true]);
        $user = User::factory()->create();
        $user->givePermissionTo('view register');
        $this->actingAs($user);

        $season = Season::factory()->create();
        $day = $this->createClosedDay($season);

        $this->post(route('bo.register.operating-days.corrections.store', $day), [
            'amount' => 10,
        ])->assertForbidden();

        $this->assertDatabaseCount('tickets', 0);
    }
}
