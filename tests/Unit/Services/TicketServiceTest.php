<?php

namespace Tests\Unit\Services;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\OperatingDay;
use App\Models\Product;
use App\Models\Season;
use App\Models\StockLocation;
use App\Models\Ticket;
use App\Services\StockService;
use App\Services\TicketCalculator;
use App\Services\TicketService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketServiceTest extends TestCase
{
    use RefreshDatabase;

    private TicketService $service;

    private TicketCalculator $calculator;

    private StockService $stockService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stockService = new StockService;
        $this->service = new TicketService($this->stockService);
        $this->calculator = $this->app->make(TicketCalculator::class);
    }

    private function makeRequest(array $data): array
    {
        return $data;
    }

    private function createOpenDay(): OperatingDay
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);

        return OperatingDay::factory()->create([
            'season_id' => $season->id,
            'closed_at' => null,
            'date' => Carbon::today('Europe/Zurich')->toDateString(),
            'total_cash' => 0,
            'total_twint' => 0,
        ]);
    }

    private function createProduct(Category $category): Product
    {
        return Product::factory()->create([
            'category_id' => $category->id,
            'unit_price' => 5.00,
        ]);
    }

    // --- create() ---

    public function test_create_creates_ticket(): void
    {
        $day = $this->createOpenDay();
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = $this->createProduct($category);
        $location = StockLocation::factory()->create();

        $request = $this->makeRequest([
            'payment_method' => 'cash',
            'operating_day_id' => $day->id,
            'sold_at' => now()->toDateTimeString(),
            'stock_location_id' => $location->id,
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ]);

        $ticket = $this->service->create($request, $day, $this->calculator);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'operating_day_id' => $day->id,
            'payment_method' => 'cash',
            'total' => 15.00,
        ]);
    }

    public function test_create_creates_ticket_items(): void
    {
        $day = $this->createOpenDay();
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = $this->createProduct($category);
        $location = StockLocation::factory()->create();

        $request = $this->makeRequest([
            'payment_method' => 'twint',
            'operating_day_id' => $day->id,
            'sold_at' => now()->toDateTimeString(),
            'stock_location_id' => $location->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $this->service->create($request, $day, $this->calculator);

        $this->assertDatabaseCount('ticket_items', 2);
    }

    public function test_create_recalculates_day_totals(): void
    {
        $day = $this->createOpenDay();
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = $this->createProduct($category);
        $location = StockLocation::factory()->create();

        $request = $this->makeRequest([
            'payment_method' => 'cash',
            'operating_day_id' => $day->id,
            'sold_at' => now()->toDateTimeString(),
            'stock_location_id' => $location->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);

        $this->service->create($request, $day, $this->calculator);

        $day->refresh();
        $this->assertEquals('10.00', $day->total_cash);
    }

    // --- create() with stock ---

    public function test_create_deducts_stock_when_location_provided(): void
    {
        $day = $this->createOpenDay();
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = $this->createProduct($category);
        $location = StockLocation::factory()->create();

        $request = $this->makeRequest([
            'payment_method' => 'cash',
            'operating_day_id' => $day->id,
            'sold_at' => now()->toDateTimeString(),
            'stock_location_id' => $location->id,
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ]);

        $ticket = $this->service->create($request, $day, $this->calculator);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'quantity' => -3,
            'type' => StockMovementType::Sale,
            'reference_type' => Ticket::class,
            'reference_id' => $ticket->id,
        ]);
    }

    public function test_create_deducts_stock_for_multiple_items(): void
    {
        $day = $this->createOpenDay();
        $category = Category::factory()->create(['is_restricted' => false]);
        $product1 = $this->createProduct($category);
        $product2 = Product::factory()->create([
            'category_id' => $category->id,
            'unit_price' => 3.00,
        ]);
        $location = StockLocation::factory()->create();

        $request = $this->makeRequest([
            'payment_method' => 'cash',
            'operating_day_id' => $day->id,
            'sold_at' => now()->toDateTimeString(),
            'stock_location_id' => $location->id,
            'items' => [
                ['product_id' => $product1->id, 'quantity' => 3],
                ['product_id' => $product2->id, 'quantity' => 1],
            ],
        ]);

        $ticket = $this->service->create($request, $day, $this->calculator);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product1->id,
            'quantity' => -3,
            'type' => StockMovementType::Sale,
            'reference_id' => $ticket->id,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product2->id,
            'quantity' => -1,
            'type' => StockMovementType::Sale,
            'reference_id' => $ticket->id,
        ]);
    }

    public function test_create_does_not_deduct_stock_for_non_stockable_product(): void
    {
        $day = $this->createOpenDay();
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = $this->createProduct($category);
        $product->update(['is_stockable' => false]);
        $location = StockLocation::factory()->create();

        $request = $this->makeRequest([
            'payment_method' => 'cash',
            'operating_day_id' => $day->id,
            'sold_at' => now()->toDateTimeString(),
            'stock_location_id' => $location->id,
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ]);

        $this->service->create($request, $day, $this->calculator);

        $this->assertDatabaseCount('stock_movements', 0);
    }

    // --- refund() ---

    public function test_refund_soft_deletes_ticket(): void
    {
        $day = $this->createOpenDay();
        $ticket = Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'total' => 25.00,
        ]);

        $this->service->refund($ticket);

        $this->assertSoftDeleted('tickets', ['id' => $ticket->id]);
    }

    public function test_refund_recalculates_day_totals(): void
    {
        $day = $this->createOpenDay();
        $ticket = Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'total' => 50.00,
        ]);

        $day->update(['total_cash' => 50.00, 'total_twint' => 0]);

        $this->service->refund($ticket);

        $day->refresh();
        $this->assertEquals('0.00', $day->total_cash);
    }

    public function test_refund_creates_compensating_stock_movements(): void
    {
        $day = $this->createOpenDay();
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = $this->createProduct($category);
        $location = StockLocation::factory()->create();

        $request = $this->makeRequest([
            'payment_method' => 'cash',
            'operating_day_id' => $day->id,
            'sold_at' => now()->toDateTimeString(),
            'stock_location_id' => $location->id,
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ]);

        $ticket = $this->service->create($request, $day, $this->calculator);

        $this->service->refund($ticket);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'quantity' => 3,
            'type' => StockMovementType::Reversal,
            'reference_type' => Ticket::class,
            'reference_id' => $ticket->id,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'quantity' => -3,
            'type' => StockMovementType::Sale,
            'reference_type' => Ticket::class,
            'reference_id' => $ticket->id,
        ]);
    }

    public function test_refund_does_not_create_stock_movements_when_no_movements(): void
    {
        $day = $this->createOpenDay();
        $location = StockLocation::factory()->create();
        $ticket = Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'total' => 50.00,
            'stock_location_id' => $location->id,
        ]);

        $this->service->refund($ticket);

        $this->assertDatabaseCount('stock_movements', 0);
    }
}
