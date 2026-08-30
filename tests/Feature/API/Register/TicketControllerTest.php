<?php

namespace Tests\Feature\API\Register;

use App\Models\Category;
use App\Models\OperatingDay;
use App\Models\Product;
use App\Models\Season;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\User;
use App\Models\Voucher;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\API\AuthTestHelper;
use Tests\TestCase;

class TicketControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private array $tokens;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('cashier');
        $this->tokens = AuthTestHelper::generateTokens($this->user);
    }

    protected function tearDown(): void
    {
        AuthTestHelper::clearUser($this->user);
        parent::tearDown();
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->tokens['accessToken']];
    }

    // --- store() ---

    public function test_store_creates_ticket_with_items(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'closed_at' => null,
        ]);
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'unit_price' => 5.00,
        ]);

        $response = $this->postJson(route('api.register.tickets.store'), [
            'payment_method' => 'cash',
            'operating_day_id' => $day->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ], $this->authHeaders());

        $response->assertCreated();
        $response->assertJsonPath('data.payment_method', 'cash');
        $this->assertDatabaseHas('tickets', [
            'operating_day_id' => $day->id,
            'total' => 10.00,
        ]);
    }

    public function test_store_computes_subtotal_correctly(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'closed_at' => null,
        ]);
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'unit_price' => 3.50,
        ]);

        $this->postJson(route('api.register.tickets.store'), [
            'payment_method' => 'twint',
            'operating_day_id' => $day->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ], $this->authHeaders())->assertCreated();

        $this->assertDatabaseHas('ticket_items', [
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 3.50,
            'subtotal' => 10.50,
        ]);
    }

    public function test_store_with_multiple_items(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'closed_at' => null,
        ]);
        $category = Category::factory()->create(['is_restricted' => false]);
        $product1 = Product::factory()->create([
            'category_id' => $category->id,
            'unit_price' => 5.00,
        ]);
        $product2 = Product::factory()->create([
            'category_id' => $category->id,
            'unit_price' => 3.00,
        ]);

        $this->postJson(route('api.register.tickets.store'), [
            'payment_method' => 'cash',
            'operating_day_id' => $day->id,
            'items' => [
                ['product_id' => $product1->id, 'quantity' => 2],
                ['product_id' => $product2->id, 'quantity' => 1],
            ],
        ], $this->authHeaders())->assertCreated();

        $this->assertDatabaseHas('tickets', ['total' => 13.00]);
        $this->assertDatabaseCount('ticket_items', 2);
    }

    public function test_store_applies_voucher_discount(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->subMonth(),
            'end_date' => Carbon::now()->addMonth(),
        ]);
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'closed_at' => null,
        ]);
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'unit_price' => 10.00,
        ]);
        $voucher = Voucher::factory()->percent()->create([
            'discount_percent' => 10.00,
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $this->postJson(route('api.register.tickets.store'), [
            'payment_method' => 'cash',
            'operating_day_id' => $day->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'voucher_id' => $voucher->id],
            ],
        ], $this->authHeaders())->assertCreated();

        $this->assertDatabaseHas('ticket_items', [
            'product_id' => $product->id,
            'voucher_id' => $voucher->id,
            'subtotal' => 9.00,
        ]);
    }

    public function test_store_with_custom_unit_price(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'closed_at' => null,
        ]);
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->allowsCustomPrice()->create([
            'category_id' => $category->id,
            'unit_price' => 10.00,
        ]);

        $this->postJson(route('api.register.tickets.store'), [
            'payment_method' => 'cash',
            'operating_day_id' => $day->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'custom_unit_price' => 7.50],
            ],
        ], $this->authHeaders())->assertCreated();

        $this->assertDatabaseHas('ticket_items', [
            'product_id' => $product->id,
            'unit_price' => 7.50,
            'subtotal' => 7.50,
        ]);
    }

    public function test_store_recalculates_day_totals(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'closed_at' => null,
            'total_cash' => 0,
            'total_twint' => 0,
        ]);
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'unit_price' => 25.00,
        ]);

        $this->postJson(route('api.register.tickets.store'), [
            'payment_method' => 'cash',
            'operating_day_id' => $day->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ], $this->authHeaders())->assertCreated();

        $this->assertEquals('25.00', $day->fresh()->total_cash);
    }

    public function test_store_fails_for_closed_operating_day(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->closed()->create([
            'season_id' => $season->id,
        ]);
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $response = $this->postJson(route('api.register.tickets.store'), [
            'payment_method' => 'cash',
            'operating_day_id' => $day->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ], $this->authHeaders());

        $response->assertUnprocessable();
    }

    public function test_store_fails_with_empty_items(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'closed_at' => null,
        ]);

        $response = $this->postJson(route('api.register.tickets.store'), [
            'payment_method' => 'cash',
            'operating_day_id' => $day->id,
            'items' => [],
        ], $this->authHeaders());

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['items']);
    }

    public function test_store_fails_with_invalid_payment_method(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'closed_at' => null,
        ]);
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $response = $this->postJson(route('api.register.tickets.store'), [
            'payment_method' => 'credit',
            'operating_day_id' => $day->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ], $this->authHeaders());

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['payment_method']);
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson(route('api.register.tickets.store'), [
            'payment_method' => 'cash',
            'operating_day_id' => 1,
            'items' => [['product_id' => 1, 'quantity' => 1]],
        ]);

        $response->assertUnauthorized();
    }

    // --- show() ---

    public function test_show_returns_ticket_with_items(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $ticket = Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'total' => 5.00,
        ]);
        TicketItem::factory()->create([
            'ticket_id' => $ticket->id,
            'product_id' => $product->id,
        ]);

        $response = $this->getJson(route('api.register.tickets.show', $ticket), $this->authHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.id', $ticket->id);
        $response->assertJsonCount(1, 'data.ticket_items');
    }

    public function test_show_returns_404_for_nonexistent_ticket(): void
    {
        $response = $this->getJson(route('api.register.tickets.show', 9999), $this->authHeaders());

        $response->assertNotFound();
    }
}
