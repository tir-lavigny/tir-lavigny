<?php

namespace Tests\Unit\Actions\Ticket;

use App\Actions\Ticket\CreateTicketAction;
use App\Http\Requests\API\Register\StoreTicketRequest;
use App\Models\Category;
use App\Models\OperatingDay;
use App\Models\Product;
use App\Models\Season;
use App\Models\StockLocation;
use App\Models\Ticket;
use App\Services\TicketCalculator;
use App\Services\TicketService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CreateTicketActionTest extends TestCase
{
    use RefreshDatabase;

    private TicketCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
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

    public function test_execute_creates_ticket(): void
    {
        $day = $this->createOpenDay();
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = $this->createProduct($category);
        $location = StockLocation::factory()->create();

        $action = $this->app->make(CreateTicketAction::class);

        $ticket = $action->execute($this->makeRequest([
            'payment_method' => 'cash',
            'operating_day_id' => $day->id,
            'sold_at' => now()->toDateTimeString(),
            'stock_location_id' => $location->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]), $day, $this->calculator);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'operating_day_id' => $day->id,
            'payment_method' => 'cash',
            'total' => 10.00,
        ]);
        $this->assertDatabaseHas('ticket_items', [
            'ticket_id' => $ticket->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_execute_delegates_to_ticket_service(): void
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
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $service = $this->app->make(TicketService::class);
        $spy = Mockery::mock(TicketService::class)->makePartial();
        $spy->shouldReceive('create')
            ->andReturnUsing(fn ($r, $d, $c) => $service->create($r, $d, $c));

        $action = new CreateTicketAction($spy);
        $ticket = $action->execute($request, $day, $this->calculator);

        $spy->shouldHaveReceived('create')->with($request, $day, $this->calculator);
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'operating_day_id' => $day->id,
            'payment_method' => 'cash',
            'total' => 5.00,
        ]);
    }

    public function test_execute_rolls_back_on_failure(): void
    {
        $day = $this->createOpenDay();
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = $this->createProduct($category);
        $location = StockLocation::factory()->create();

        $spy = Mockery::mock(TicketService::class);
        $spy->shouldReceive('create')->andReturnUsing(function ($r, $d, $c) use ($day) {
            Ticket::create([
                'total' => 5.00,
                'payment_method' => 'cash',
                'operating_day_id' => $day->id,
                'sold_at' => now()->toDateTimeString(),
                'stock_location_id' => 1,
            ]);
            throw new \Exception('failure');
        });

        $action = new CreateTicketAction($spy);

        try {
            $action->execute($this->makeRequest([
                'payment_method' => 'cash',
                'operating_day_id' => $day->id,
                'sold_at' => now()->toDateTimeString(),
                'stock_location_id' => $location->id,
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ]), $day, $this->calculator);
        } catch (\Exception $e) {
            $this->assertSame('failure', $e->getMessage());
        }

        $this->assertDatabaseCount('tickets', 0);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
