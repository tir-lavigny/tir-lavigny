<?php

namespace Tests\Unit\Http\Controllers\API\Register;

use App\Actions\Ticket\CreateTicketAction;
use App\Actions\Ticket\RefundTicketAction;
use App\Http\Controllers\API\Register\TicketController;
use App\Http\Requests\API\Register\StoreTicketRequest;
use App\Models\Category;
use App\Models\OperatingDay;
use App\Models\Product;
use App\Models\Season;
use App\Models\StockLocation;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\User;
use App\Models\Voucher;
use App\Services\TicketCalculator;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketControllerTest extends TestCase
{
    use RefreshDatabase;

    private TicketController $controller;

    private CreateTicketAction $createTicketAction;

    private RefundTicketAction $refundTicketAction;

    private TicketCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->controller = $this->app->make(TicketController::class);
        $this->createTicketAction = $this->app->make(CreateTicketAction::class);
        $this->refundTicketAction = $this->app->make(RefundTicketAction::class);
        $this->calculator = $this->app->make(TicketCalculator::class);
    }

    private function storeRequest(array $data = []): StoreTicketRequest
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $request = new StoreTicketRequest;
        $request->merge($data);
        $request->setUserResolver(fn () => $user);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $request->validateResolved();

        return $request;
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
            'date' => Carbon::today('Europe/Zurich')->toDateString(),
        ]);
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'unit_price' => 5.00,
        ]);

        $response = $this->controller->store($this->createTicketAction, $this->storeRequest([
            'payment_method' => 'cash',
            'operating_day_id' => $day->id,
            'stock_location_id' => StockLocation::factory()->create()->id,
            'sold_at' => now()->toDateTimeString(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]), $this->calculator);

        $this->assertEquals(201, $response->status());
        $this->assertDatabaseHas('tickets', [
            'operating_day_id' => $day->id,
            'payment_method' => 'cash',
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
            'date' => Carbon::today('Europe/Zurich')->toDateString(),
        ]);
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'unit_price' => 3.50,
        ]);

        $this->controller->store($this->createTicketAction, $this->storeRequest([
            'payment_method' => 'twint',
            'operating_day_id' => $day->id,
            'stock_location_id' => StockLocation::factory()->create()->id,
            'sold_at' => now()->toDateTimeString(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ]), $this->calculator);

        $this->assertDatabaseHas('ticket_items', [
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 3.50,
            'subtotal' => 10.50,
        ]);
        $this->assertDatabaseHas('tickets', [
            'total' => 10.50,
            'payment_method' => 'twint',
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
            'date' => Carbon::today('Europe/Zurich')->toDateString(),
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

        $this->controller->store($this->createTicketAction, $this->storeRequest([
            'payment_method' => 'cash',
            'operating_day_id' => $day->id,
            'stock_location_id' => StockLocation::factory()->create()->id,
            'sold_at' => now()->toDateTimeString(),
            'items' => [
                ['product_id' => $product1->id, 'quantity' => 2],
                ['product_id' => $product2->id, 'quantity' => 1],
            ],
        ]), $this->calculator);

        $this->assertDatabaseHas('tickets', [
            'total' => 13.00,
            'payment_method' => 'cash',
        ]);
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
            'date' => Carbon::today('Europe/Zurich')->toDateString(),
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

        $this->controller->store($this->createTicketAction, $this->storeRequest([
            'payment_method' => 'cash',
            'operating_day_id' => $day->id,
            'stock_location_id' => StockLocation::factory()->create()->id,
            'sold_at' => now()->toDateTimeString(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'voucher_ids' => [$voucher->id]],
            ],
        ]), $this->calculator);

        $this->assertDatabaseHas('ticket_items', [
            'product_id' => $product->id,
            'voucher_id' => $voucher->id,
            'subtotal' => 9.00,
        ]);
    }

    public function test_store_expands_multiple_vouchers_into_unit_rows(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->subMonth(),
            'end_date' => Carbon::now()->addMonth(),
        ]);
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'closed_at' => null,
            'date' => Carbon::today('Europe/Zurich')->toDateString(),
        ]);
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'unit_price' => 10.00,
        ]);
        $voucher1 = Voucher::factory()->percent()->create([
            'discount_percent' => 100.00,
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);
        $voucher2 = Voucher::factory()->percent()->create([
            'discount_percent' => 100.00,
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $response = $this->controller->store($this->createTicketAction, $this->storeRequest([
            'payment_method' => 'cash',
            'operating_day_id' => $day->id,
            'stock_location_id' => StockLocation::factory()->create()->id,
            'sold_at' => now()->toDateTimeString(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3, 'voucher_ids' => [$voucher1->id, $voucher2->id]],
            ],
        ]), $this->calculator);

        $this->assertEquals(201, $response->status());
        $this->assertDatabaseHas('tickets', ['total' => 10.00]);
        $this->assertDatabaseCount('ticket_items', 3);
        $this->assertDatabaseHas('ticket_items', [
            'voucher_id' => $voucher1->id,
            'quantity' => 1,
            'subtotal' => 0.00,
        ]);
        $this->assertDatabaseHas('ticket_items', [
            'voucher_id' => $voucher2->id,
            'quantity' => 1,
            'subtotal' => 0.00,
        ]);
        $this->assertDatabaseHas('ticket_items', [
            'voucher_id' => null,
            'quantity' => 1,
            'subtotal' => 10.00,
        ]);
    }

    public function test_store_allows_reuse_of_refunded_voucher(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->subMonth(),
            'end_date' => Carbon::now()->addMonth(),
        ]);
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'closed_at' => null,
            'date' => Carbon::today('Europe/Zurich')->toDateString(),
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

        $ticket = Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'stock_location_id' => StockLocation::factory()->create()->id,
            'total' => 9.00,
        ]);
        TicketItem::factory()->create([
            'ticket_id' => $ticket->id,
            'product_id' => $product->id,
            'voucher_id' => $voucher->id,
            'quantity' => 1,
            'unit_price' => 10.00,
            'subtotal' => 9.00,
        ]);

        $ticket->delete();

        $response = $this->controller->store($this->createTicketAction, $this->storeRequest([
            'payment_method' => 'cash',
            'operating_day_id' => $day->id,
            'stock_location_id' => StockLocation::factory()->create()->id,
            'sold_at' => now()->toDateTimeString(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'voucher_ids' => [$voucher->id]],
            ],
        ]), $this->calculator);

        $this->assertEquals(201, $response->status());
        $this->assertDatabaseHas('ticket_items', [
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
            'date' => Carbon::today('Europe/Zurich')->toDateString(),
        ]);
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->allowsCustomPrice()->create([
            'category_id' => $category->id,
            'unit_price' => 10.00,
        ]);

        $this->controller->store($this->createTicketAction, $this->storeRequest([
            'payment_method' => 'cash',
            'operating_day_id' => $day->id,
            'stock_location_id' => StockLocation::factory()->create()->id,
            'sold_at' => now()->toDateTimeString(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'custom_unit_price' => 7.50],
            ],
        ]), $this->calculator);

        $this->assertDatabaseHas('ticket_items', [
            'product_id' => $product->id,
            'unit_price' => 7.50,
            'subtotal' => 7.50,
        ]);
    }

    public function test_store_allows_negative_custom_unit_price(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'closed_at' => null,
            'date' => Carbon::today('Europe/Zurich')->toDateString(),
        ]);
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->allowsCustomPrice()->create([
            'category_id' => $category->id,
            'unit_price' => 0.00,
        ]);

        $this->controller->store($this->createTicketAction, $this->storeRequest([
            'payment_method' => 'cash',
            'operating_day_id' => $day->id,
            'stock_location_id' => StockLocation::factory()->create()->id,
            'sold_at' => now()->toDateTimeString(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'custom_unit_price' => -25.00],
            ],
        ]), $this->calculator);

        $this->assertDatabaseHas('ticket_items', [
            'product_id' => $product->id,
            'unit_price' => -25.00,
            'subtotal' => -25.00,
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
            'date' => Carbon::today('Europe/Zurich')->toDateString(),
            'total_cash' => 0,
            'total_twint' => 0,
        ]);
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'unit_price' => 25.00,
        ]);

        $this->controller->store($this->createTicketAction, $this->storeRequest([
            'payment_method' => 'cash',
            'operating_day_id' => $day->id,
            'stock_location_id' => StockLocation::factory()->create()->id,
            'sold_at' => now()->toDateTimeString(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]), $this->calculator);

        $day->refresh();
        $this->assertEquals('25.00', $day->total_cash);
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

        $response = $this->controller->store($this->createTicketAction, $this->storeRequest([
            'payment_method' => 'cash',
            'operating_day_id' => $day->id,
            'stock_location_id' => StockLocation::factory()->create()->id,
            'sold_at' => now()->toDateTimeString(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]), $this->calculator);

        $this->assertEquals(422, $response->status());
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
            'stock_location_id' => StockLocation::factory()->create()->id,
            'total' => 5.00,
        ]);
        TicketItem::factory()->create([
            'ticket_id' => $ticket->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 5.00,
            'subtotal' => 5.00,
        ]);

        $response = $this->controller->show($ticket);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->status());
        $this->assertEquals($ticket->id, $data['data']['id']);
        $this->assertCount(1, $data['data']['ticket_items']);
        $this->assertEquals($product->name, $data['data']['ticket_items'][0]['product']['name']);
    }

    public function test_show_includes_refunded_ticket_items(): void
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
            'stock_location_id' => StockLocation::factory()->create()->id,
        ]);
        TicketItem::factory()->create([
            'ticket_id' => $ticket->id,
            'product_id' => $product->id,
        ]);
        $ticket->delete();

        $response = $this->controller->show($ticket);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->status());
        $this->assertCount(1, $data['data']['ticket_items']);
    }

    // --- refund() ---

    public function test_refund_soft_deletes_ticket(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'closed_at' => null,
            'date' => Carbon::today('Europe/Zurich')->toDateString(),
        ]);
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $ticket = Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'stock_location_id' => StockLocation::factory()->create()->id,
            'total' => 5.00,
        ]);
        TicketItem::factory()->create([
            'ticket_id' => $ticket->id,
            'product_id' => $product->id,
        ]);

        $response = $this->controller->refund($this->refundTicketAction, $ticket);

        $this->assertEquals(200, $response->status());
        $this->assertSoftDeleted('tickets', ['id' => $ticket->id]);
    }

    public function test_refund_recalculates_day_totals(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'closed_at' => null,
            'date' => Carbon::today('Europe/Zurich')->toDateString(),
            'total_cash' => 5.00,
            'total_twint' => 0,
        ]);
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $ticket = Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'stock_location_id' => StockLocation::factory()->create()->id,
            'total' => 5.00,
        ]);
        TicketItem::factory()->create([
            'ticket_id' => $ticket->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 5.00,
            'subtotal' => 5.00,
        ]);

        $this->controller->refund($this->refundTicketAction, $ticket);

        $day->refresh();
        $this->assertEquals('0.00', $day->total_cash);
    }

    public function test_refund_fails_for_closed_operating_day(): void
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
        $ticket = Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'stock_location_id' => StockLocation::factory()->create()->id,
            'total' => 5.00,
        ]);

        $response = $this->controller->refund($this->refundTicketAction, $ticket);

        $this->assertEquals(422, $response->status());
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'deleted_at' => null]);
    }
}
