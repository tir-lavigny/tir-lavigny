<?php

namespace Tests\Unit\Services;

use App\Http\Requests\API\Register\StoreTicketRequest;
use App\Models\Category;
use App\Models\OperatingDay;
use App\Models\Product;
use App\Models\Season;
use App\Models\Ticket;
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TicketService;
        $this->calculator = $this->app->make(TicketCalculator::class);
    }

    private function makeRequest(array $data): StoreTicketRequest
    {
        $request = new StoreTicketRequest;
        $request->merge($data);

        return $request;
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
            'date' => Carbon::today()->toDateString(),
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

        $request = $this->makeRequest([
            'payment_method' => 'cash',
            'operating_day_id' => $day->id,
            'sold_at' => now()->toDateTimeString(),
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

        $request = $this->makeRequest([
            'payment_method' => 'twint',
            'operating_day_id' => $day->id,
            'sold_at' => now()->toDateTimeString(),
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

        $request = $this->makeRequest([
            'payment_method' => 'cash',
            'operating_day_id' => $day->id,
            'sold_at' => now()->toDateTimeString(),
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);

        $this->service->create($request, $day, $this->calculator);

        $day->refresh();
        $this->assertEquals('10.00', $day->total_cash);
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
}
