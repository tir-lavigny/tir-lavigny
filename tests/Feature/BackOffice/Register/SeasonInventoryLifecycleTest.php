<?php

namespace Tests\Feature\BackOffice\Register;

use App\Enums\StockCountType;
use App\Enums\StockMovementType;
use App\Http\Requests\API\Register\StoreTicketRequest;
use App\Models\Category;
use App\Models\OperatingDay;
use App\Models\Product;
use App\Models\Season;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Ticket;
use App\Services\StockService;
use App\Services\TicketCalculator;
use App\Services\TicketService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SeasonInventoryLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stockService;

    private TicketService $ticketService;

    private TicketCalculator $calculator;

    private Season $season;

    private StockLocation $warehouse;

    private StockLocation $bar;

    private Product $beer;

    private Product $coffee;

    private Product $correction;

    private OperatingDay $day;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stockService = new StockService;
        $this->ticketService = new TicketService($this->stockService);
        $this->calculator = $this->app->make(TicketCalculator::class);

        $this->season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
            'initial_balance' => 0,
        ]);

        $this->warehouse = StockLocation::factory()->create(['name' => 'Warehouse']);
        $this->bar = StockLocation::factory()->create(['name' => 'Bar']);

        $category = Category::factory()->create(['is_restricted' => false]);

        $this->beer = Product::factory()->create([
            'name' => 'Beer',
            'unit_price' => 5.00,
            'category_id' => $category->id,
            'is_stockable' => true,
        ]);

        $this->coffee = Product::factory()->create([
            'name' => 'Coffee',
            'unit_price' => 3.00,
            'category_id' => $category->id,
            'is_stockable' => true,
        ]);

        $this->correction = Product::factory()->create([
            'name' => 'Correction',
            'unit_price' => 0,
            'allows_custom_price' => true,
            'category_id' => $category->id,
            'is_stockable' => false,
        ]);

        $this->day = OperatingDay::factory()->create([
            'season_id' => $this->season->id,
            'date' => Carbon::today('Europe/Zurich')->toDateString(),
            'closed_at' => null,
            'total_cash' => 0,
            'total_twint' => 0,
        ]);
    }

    private function currentStock(Product $product, StockLocation $location): int
    {
        return (int) StockMovement::where('product_id', $product->id)
            ->where('stock_location_id', $location->id)
            ->sum('quantity');
    }

    private function movementsFor(Product $product, StockLocation $location): Collection
    {
        return StockMovement::where('product_id', $product->id)
            ->where('stock_location_id', $location->id)
            ->orderBy('id')
            ->get();
    }

    private function makeTicketRequest(array $items, StockLocation $location): StoreTicketRequest
    {
        $request = new StoreTicketRequest;
        $request->merge([
            'payment_method' => 'cash',
            'operating_day_id' => $this->day->id,
            'sold_at' => now()->toDateTimeString(),
            'stock_location_id' => $location->id,
            'items' => $items,
        ]);

        return $request;
    }

    private function seedStockAt(StockLocation $location, array $items): void
    {
        foreach ($items as [$product, $quantity]) {
            $this->stockService->addMovement(
                product: $product,
                location: $location,
                quantity: $quantity,
                type: StockMovementType::Transfer,
                season: $this->season,
            );
        }
    }

    private function completeStockCount(StockLocation $location, StockCountType $type, array $lines): StockCount
    {
        $count = StockCount::create([
            'season_id' => $this->season->id,
            'stock_location_id' => $location->id,
            'type' => $type,
            'status' => 'pending',
        ]);

        foreach ($lines as [$product, $expected, $counted]) {
            StockCountLine::create([
                'stock_count_id' => $count->id,
                'product_id' => $product->id,
                'expected_quantity' => $expected,
                'counted_quantity' => $counted,
            ]);
        }

        $count->update(['status' => 'completed', 'completed_at' => now()]);

        foreach ($lines as [$product, $expected, $counted]) {
            $diff = $counted - $expected;
            if ($diff !== 0) {
                $this->stockService->addMovement(
                    product: $product,
                    location: $location,
                    quantity: $diff,
                    type: StockMovementType::Adjustment,
                    reference: $count,
                    season: $this->season,
                );
            }
        }

        return $count;
    }

    public function test_full_inventory_lifecycle(): void
    {
        // ─── Step 1: Initial stock count at Warehouse ───────────────────
        $this->completeStockCount($this->warehouse, StockCountType::Initial, [
            [$this->beer, 0, 100],
            [$this->coffee, 0, 50],
        ]);

        $this->assertEquals(100, $this->currentStock($this->beer, $this->warehouse));
        $this->assertEquals(50, $this->currentStock($this->coffee, $this->warehouse));

        $this->assertCount(0, StockMovement::where('product_id', $this->correction->id)
            ->where('stock_location_id', $this->warehouse->id)
            ->get());
        $this->assertDatabaseCount('stock_movements', 2);

        $beerMovements = $this->movementsFor($this->beer, $this->warehouse);
        $this->assertCount(1, $beerMovements);
        $this->assertEquals(StockMovementType::Adjustment, $beerMovements->first()->type);
        $this->assertGreaterThan(0, $beerMovements->first()->quantity);

        $coffeeMovements = $this->movementsFor($this->coffee, $this->warehouse);
        $this->assertCount(1, $coffeeMovements);
        $this->assertEquals(StockMovementType::Adjustment, $coffeeMovements->first()->type);
        $this->assertGreaterThan(0, $coffeeMovements->first()->quantity);

        // ─── Step 2: Transfer from Warehouse to Bar ─────────────────────
        $transfer = StockTransfer::create([
            'from_location_id' => $this->warehouse->id,
            'to_location_id' => $this->bar->id,
            'season_id' => $this->season->id,
            'reason' => 'Restock bar',
        ]);

        $transfer->stockTransferLines()->create([
            'product_id' => $this->beer->id,
            'quantity' => 20,
        ]);

        $transfer->stockTransferLines()->create([
            'product_id' => $this->coffee->id,
            'quantity' => 10,
        ]);

        $this->stockService->addMovement(product: $this->beer, location: $this->warehouse, quantity: -20, type: StockMovementType::Transfer, reference: $transfer, season: $this->season);
        $this->stockService->addMovement(product: $this->coffee, location: $this->warehouse, quantity: -10, type: StockMovementType::Transfer, reference: $transfer, season: $this->season);
        $this->stockService->addMovement(product: $this->beer, location: $this->bar, quantity: 20, type: StockMovementType::Transfer, reference: $transfer, season: $this->season);
        $this->stockService->addMovement(product: $this->coffee, location: $this->bar, quantity: 10, type: StockMovementType::Transfer, reference: $transfer, season: $this->season);

        $this->assertEquals(80, $this->currentStock($this->beer, $this->warehouse));
        $this->assertEquals(40, $this->currentStock($this->coffee, $this->warehouse));
        $this->assertEquals(20, $this->currentStock($this->beer, $this->bar));
        $this->assertEquals(10, $this->currentStock($this->coffee, $this->bar));

        $warehouseBeerMovements = $this->movementsFor($this->beer, $this->warehouse);
        $transferOutBeer = $warehouseBeerMovements->where('type', StockMovementType::Transfer)->first();
        $this->assertEquals(-20, $transferOutBeer->quantity);
        $this->assertEquals(StockTransfer::class, $transferOutBeer->reference_type);
        $this->assertEquals($transfer->id, $transferOutBeer->reference_id);

        $barBeerMovements = $this->movementsFor($this->beer, $this->bar);
        $transferInBeer = $barBeerMovements->where('type', StockMovementType::Transfer)->first();
        $this->assertEquals(20, $transferInBeer->quantity);
        $this->assertEquals(StockTransfer::class, $transferInBeer->reference_type);
        $this->assertEquals($transfer->id, $transferInBeer->reference_id);

        // ─── Step 3: Sell products at Bar ───────────────────────────────
        $ticket1 = $this->ticketService->create(
            $this->makeTicketRequest([
                ['product_id' => $this->beer->id, 'quantity' => 3],
                ['product_id' => $this->coffee->id, 'quantity' => 2],
                ['product_id' => $this->correction->id, 'quantity' => 1, 'custom_unit_price' => 0],
            ], $this->bar),
            $this->day,
            $this->calculator,
        );

        $this->assertEquals($this->bar->id, $ticket1->stock_location_id);
        $this->assertEquals(17, $this->currentStock($this->beer, $this->bar));
        $this->assertEquals(8, $this->currentStock($this->coffee, $this->bar));

        $this->assertCount(0, StockMovement::where('product_id', $this->correction->id)
            ->where('stock_location_id', $this->bar->id)
            ->get());
        $this->assertDatabaseCount('stock_movements', 8);

        $ticket1Movements = StockMovement::where('reference_type', Ticket::class)
            ->where('reference_id', $ticket1->id)
            ->get();

        $this->assertCount(2, $ticket1Movements);

        $beerSale = $ticket1Movements->where('product_id', $this->beer->id)->first();
        $this->assertEquals(StockMovementType::Sale, $beerSale->type);
        $this->assertEquals(-3, $beerSale->quantity);
        $this->assertEquals($this->bar->id, $beerSale->stock_location_id);
        $this->assertEquals(Ticket::class, $beerSale->reference_type);
        $this->assertEquals($ticket1->id, $beerSale->reference_id);

        $coffeeSale = $ticket1Movements->where('product_id', $this->coffee->id)->first();
        $this->assertEquals(StockMovementType::Sale, $coffeeSale->type);
        $this->assertEquals(-2, $coffeeSale->quantity);
        $this->assertEquals($this->bar->id, $coffeeSale->stock_location_id);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket1->id,
            'stock_location_id' => $this->bar->id,
        ]);
        $this->assertEquals(3, $ticket1->ticketItems()->count());

        // ─── Step 4: Refund/correct the ticket ──────────────────────────
        $this->ticketService->refund($ticket1);

        $this->assertEquals(20, $this->currentStock($this->beer, $this->bar));
        $this->assertEquals(10, $this->currentStock($this->coffee, $this->bar));

        $this->assertDatabaseCount('stock_movements', 10);

        $beerSale->refresh();
        $this->assertEquals(-3, $beerSale->quantity);
        $this->assertEquals(StockMovementType::Sale, $beerSale->type);

        $coffeeSale->refresh();
        $this->assertEquals(-2, $coffeeSale->quantity);
        $this->assertEquals(StockMovementType::Sale, $coffeeSale->type);

        $beerReversal = StockMovement::where('product_id', $this->beer->id)
            ->where('stock_location_id', $this->bar->id)
            ->where('type', StockMovementType::Reversal)
            ->first();
        $this->assertNotNull($beerReversal);
        $this->assertEquals(3, $beerReversal->quantity);
        $this->assertEquals(Ticket::class, $beerReversal->reference_type);
        $this->assertEquals($ticket1->id, $beerReversal->reference_id);
        $this->assertStringContainsString(
            "Reversal of stock movement #{$beerSale->id}",
            $beerReversal->reason,
        );

        $coffeeReversal = StockMovement::where('product_id', $this->coffee->id)
            ->where('stock_location_id', $this->bar->id)
            ->where('type', StockMovementType::Reversal)
            ->first();
        $this->assertNotNull($coffeeReversal);
        $this->assertEquals(2, $coffeeReversal->quantity);
        $this->assertStringContainsString(
            "Reversal of stock movement #{$coffeeSale->id}",
            $coffeeReversal->reason,
        );

        $this->assertCount(0, StockMovement::where('product_id', $this->correction->id)
            ->where('stock_location_id', $this->bar->id)
            ->get());

        // ─── Step 5: Double reversal throws and leaves stock untouched ──
        $stockBeforeBeer = $this->currentStock($this->beer, $this->bar);
        $stockBeforeCoffee = $this->currentStock($this->coffee, $this->bar);
        $movementsBefore = StockMovement::count();

        try {
            $this->ticketService->refund($ticket1);
            $this->fail('Expected HttpException for double refund');
        } catch (HttpException $e) {
            $this->assertEquals(422, $e->getStatusCode());
            $this->assertStringContainsString('already refunded', $e->getMessage());
        }

        $this->assertEquals($stockBeforeBeer, $this->currentStock($this->beer, $this->bar));
        $this->assertEquals($stockBeforeCoffee, $this->currentStock($this->coffee, $this->bar));
        $this->assertEquals($movementsBefore, StockMovement::count());

        // ─── Step 6: Sell again after correction ────────────────────────
        $ticket2 = $this->ticketService->create(
            $this->makeTicketRequest([
                ['product_id' => $this->beer->id, 'quantity' => 5],
                ['product_id' => $this->coffee->id, 'quantity' => 1],
            ], $this->bar),
            $this->day,
            $this->calculator,
        );

        $this->assertEquals(15, $this->currentStock($this->beer, $this->bar));
        $this->assertEquals(9, $this->currentStock($this->coffee, $this->bar));

        $ticket2Movements = StockMovement::where('reference_type', Ticket::class)
            ->where('reference_id', $ticket2->id)
            ->get();

        $this->assertCount(2, $ticket2Movements);
        $this->assertEquals(-5, $ticket2Movements->where('product_id', $this->beer->id)->first()->quantity);
        $this->assertEquals(-1, $ticket2Movements->where('product_id', $this->coffee->id)->first()->quantity);

        // ─── Step 7: Register stock loss at Bar ─────────────────────────
        $this->stockService->addMovement(
            product: $this->beer,
            location: $this->bar,
            quantity: -2,
            type: StockMovementType::Loss,
            season: $this->season,
            reason: 'Broken bottles',
        );

        $this->assertEquals(13, $this->currentStock($this->beer, $this->bar));
        $this->assertEquals(9, $this->currentStock($this->coffee, $this->bar));

        $lossMovement = StockMovement::where('product_id', $this->beer->id)
            ->where('stock_location_id', $this->bar->id)
            ->where('type', StockMovementType::Loss)
            ->first();

        $this->assertNotNull($lossMovement);
        $this->assertEquals(-2, $lossMovement->quantity);
        $this->assertEquals($this->bar->id, $lossMovement->stock_location_id);
        $this->assertEquals('Broken bottles', $lossMovement->reason);

        // ─── Step 8: Mid-season stock count at Bar ──────────────────────
        $this->completeStockCount($this->bar, StockCountType::Adjustment, [
            [$this->beer, 13, 12],
            [$this->coffee, 9, 9],
        ]);

        $this->assertEquals(12, $this->currentStock($this->beer, $this->bar));
        $this->assertEquals(9, $this->currentStock($this->coffee, $this->bar));

        $midAdjustments = StockMovement::where('stock_location_id', $this->bar->id)
            ->where('type', StockMovementType::Adjustment)
            ->get();

        $this->assertCount(1, $midAdjustments);
        $this->assertEquals($this->beer->id, $midAdjustments->first()->product_id);
        $this->assertEquals(-1, $midAdjustments->first()->quantity);

        // ─── Step 9: Final stock count at Bar ───────────────────────────
        $this->completeStockCount($this->bar, StockCountType::Final, [
            [$this->beer, 12, 12],
            [$this->coffee, 9, 8],
        ]);

        $this->assertEquals(12, $this->currentStock($this->beer, $this->bar));
        $this->assertEquals(8, $this->currentStock($this->coffee, $this->bar));

        // ─── Step 10: Final ledger verification ─────────────────────────
        //
        // Warehouse Beer:  +100 (initial) -20 (transfer) = 80
        // Warehouse Coffee: +50 (initial) -10 (transfer) = 40
        // Bar Beer:        +20 (transfer) -3 (sale) +3 (reversal) -5 (sale) -2 (loss) -1 (count) = 12
        // Bar Coffee:      +10 (transfer) -2 (sale) +2 (reversal) -1 (sale) -1 (final count) = 8
        // Correction:      zero movements

        $this->assertEquals(80, $this->currentStock($this->beer, $this->warehouse));
        $this->assertEquals(40, $this->currentStock($this->coffee, $this->warehouse));
        $this->assertEquals(12, $this->currentStock($this->beer, $this->bar));
        $this->assertEquals(8, $this->currentStock($this->coffee, $this->bar));

        $this->assertCount(0, StockMovement::where('product_id', $this->correction->id)->get());

        // Beer at Bar: exact movement breakdown
        $barBeer = $this->movementsFor($this->beer, $this->bar);
        $this->assertEquals(12, $barBeer->sum('quantity'));

        $barBeerByType = $barBeer->groupBy(fn ($m) => $m->type->value);
        $this->assertEquals([20], $barBeerByType['transfer']->pluck('quantity')->all());
        $this->assertEquals([-3, -5], $barBeerByType['sale']->pluck('quantity')->all());
        $this->assertEquals([3], $barBeerByType['reversal']->pluck('quantity')->all());
        $this->assertEquals([-2], $barBeerByType['loss']->pluck('quantity')->all());
        $this->assertEquals([-1], $barBeerByType['adjustment']->pluck('quantity')->all());

        // Coffee at Bar: exact movement breakdown
        $barCoffee = $this->movementsFor($this->coffee, $this->bar);
        $this->assertEquals(8, $barCoffee->sum('quantity'));

        $barCoffeeByType = $barCoffee->groupBy(fn ($m) => $m->type->value);
        $this->assertEquals([10], $barCoffeeByType['transfer']->pluck('quantity')->all());
        $this->assertEquals([-2, -1], $barCoffeeByType['sale']->pluck('quantity')->all());
        $this->assertEquals([2], $barCoffeeByType['reversal']->pluck('quantity')->all());
        $this->assertEquals([-1], $barCoffeeByType['adjustment']->pluck('quantity')->all());

        // Beer at Warehouse: exact movement breakdown
        $whBeer = $this->movementsFor($this->beer, $this->warehouse);
        $this->assertEquals(80, $whBeer->sum('quantity'));

        $whBeerByType = $whBeer->groupBy(fn ($m) => $m->type->value);
        $this->assertEquals([100], $whBeerByType['adjustment']->pluck('quantity')->all());
        $this->assertEquals([-20], $whBeerByType['transfer']->pluck('quantity')->all());

        // Coffee at Warehouse: exact movement breakdown
        $whCoffee = $this->movementsFor($this->coffee, $this->warehouse);
        $this->assertEquals(40, $whCoffee->sum('quantity'));

        $whCoffeeByType = $whCoffee->groupBy(fn ($m) => $m->type->value);
        $this->assertEquals([50], $whCoffeeByType['adjustment']->pluck('quantity')->all());
        $this->assertEquals([-10], $whCoffeeByType['transfer']->pluck('quantity')->all());
    }

    public function test_refund_reverses_sale_movements_and_blocks_double_reversal(): void
    {
        $this->seedStockAt($this->bar, [
            [$this->beer, 20],
            [$this->coffee, 10],
        ]);

        $ticket = $this->ticketService->create(
            $this->makeTicketRequest([
                ['product_id' => $this->beer->id, 'quantity' => 3],
                ['product_id' => $this->coffee->id, 'quantity' => 2],
            ], $this->bar),
            $this->day,
            $this->calculator,
        );

        $beerSale = StockMovement::where('product_id', $this->beer->id)
            ->where('stock_location_id', $this->bar->id)
            ->where('type', StockMovementType::Sale)
            ->first();

        $coffeeSale = StockMovement::where('product_id', $this->coffee->id)
            ->where('stock_location_id', $this->bar->id)
            ->where('type', StockMovementType::Sale)
            ->first();

        // Refund succeeds
        $this->ticketService->refund($ticket);

        // Stock restored
        $this->assertEquals(20, $this->currentStock($this->beer, $this->bar));
        $this->assertEquals(10, $this->currentStock($this->coffee, $this->bar));

        // Original sale movements untouched
        $beerSale->refresh();
        $coffeeSale->refresh();
        $this->assertEquals(-3, $beerSale->quantity);
        $this->assertEquals(StockMovementType::Sale, $beerSale->type);
        $this->assertEquals(-2, $coffeeSale->quantity);
        $this->assertEquals(StockMovementType::Sale, $coffeeSale->type);

        // Reversal movements created with link to originals
        $beerReversal = StockMovement::where('product_id', $this->beer->id)
            ->where('stock_location_id', $this->bar->id)
            ->where('type', StockMovementType::Reversal)
            ->first();
        $this->assertNotNull($beerReversal);
        $this->assertEquals(3, $beerReversal->quantity);
        $this->assertStringContainsString(
            "Reversal of stock movement #{$beerSale->id}",
            $beerReversal->reason,
        );

        $coffeeReversal = StockMovement::where('product_id', $this->coffee->id)
            ->where('stock_location_id', $this->bar->id)
            ->where('type', StockMovementType::Reversal)
            ->first();
        $this->assertNotNull($coffeeReversal);
        $this->assertEquals(2, $coffeeReversal->quantity);
        $this->assertStringContainsString(
            "Reversal of stock movement #{$coffeeSale->id}",
            $coffeeReversal->reason,
        );

        // No movement for non-stockable product
        $this->assertCount(0, StockMovement::where('product_id', $this->correction->id)
            ->where('stock_location_id', $this->bar->id)
            ->get());

        // Double reversal throws
        $movementsBefore = StockMovement::count();
        $stockBeforeBeer = $this->currentStock($this->beer, $this->bar);
        $stockBeforeCoffee = $this->currentStock($this->coffee, $this->bar);

        try {
            $this->ticketService->refund($ticket);
            $this->fail('Expected HttpException for double refund');
        } catch (HttpException $e) {
            $this->assertEquals(422, $e->getStatusCode());
            $this->assertStringContainsString('already refunded', $e->getMessage());
        }

        // No new movements, stock unchanged
        $this->assertEquals($movementsBefore, StockMovement::count());
        $this->assertEquals($stockBeforeBeer, $this->currentStock($this->beer, $this->bar));
        $this->assertEquals($stockBeforeCoffee, $this->currentStock($this->coffee, $this->bar));
    }

    public function test_immutability_of_stock_movements(): void
    {
        $ticket = $this->ticketService->create(
            $this->makeTicketRequest([
                ['product_id' => $this->beer->id, 'quantity' => 3],
                ['product_id' => $this->coffee->id, 'quantity' => 2],
            ], $this->bar),
            $this->day,
            $this->calculator,
        );

        $beerSale = StockMovement::where('product_id', $this->beer->id)
            ->where('stock_location_id', $this->bar->id)
            ->where('type', StockMovementType::Sale)
            ->first();

        $coffeeSale = StockMovement::where('product_id', $this->coffee->id)
            ->where('stock_location_id', $this->bar->id)
            ->where('type', StockMovementType::Sale)
            ->first();

        $originalBeerQty = $beerSale->quantity;
        $originalCoffeeQty = $coffeeSale->quantity;
        $originalBeerType = $beerSale->type;
        $originalCoffeeType = $coffeeSale->type;

        $this->ticketService->refund($ticket);

        $beerSale->refresh();
        $coffeeSale->refresh();

        $this->assertEquals($originalBeerQty, $beerSale->quantity);
        $this->assertEquals($originalCoffeeQty, $coffeeSale->quantity);
        $this->assertEquals($originalBeerType, $beerSale->type);
        $this->assertEquals($originalCoffeeType, $coffeeSale->type);

        $this->expectException(LogicException::class);
        $beerSale->delete();
    }
}
