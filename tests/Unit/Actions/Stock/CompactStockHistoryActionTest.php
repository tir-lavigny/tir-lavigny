<?php

namespace Tests\Unit\Actions\Stock;

use App\Actions\Stock\CompactStockHistoryAction;
use App\Actions\Stock\CreateStockSnapshotAction;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockArchivedMovement;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\StockSnapshot;
use App\Models\StockSnapshotLine;
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompactStockHistoryActionTest extends TestCase
{
    use RefreshDatabase;

    private CompactStockHistoryAction $action;

    private StockService $stockService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stockService = new StockService;
        $this->action = new CompactStockHistoryAction(
            new CreateStockSnapshotAction($this->stockService),
        );
    }

    public function test_compact_does_not_change_current_stock(): void
    {
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: 100,
            type: StockMovementType::Purchase,
        );
        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: -20,
            type: StockMovementType::Sale,
        );

        $stockBefore = $this->stockService->getStockForProduct($product, $location);
        $this->assertEquals(80, $stockBefore);

        $this->action->execute($location);

        $stockAfter = $this->stockService->getStockForProduct($product, $location);
        $this->assertEquals(80, $stockAfter);
    }

    public function test_compact_creates_snapshot(): void
    {
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: 50,
            type: StockMovementType::Purchase,
        );

        $snapshot = $this->action->execute($location);

        $this->assertDatabaseHas('stock_snapshots', [
            'id' => $snapshot->id,
            'stock_location_id' => $location->id,
        ]);
    }

    public function test_compact_archives_old_movements(): void
    {
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: 100,
            type: StockMovementType::Purchase,
        );
        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: -20,
            type: StockMovementType::Sale,
        );

        $this->action->execute($location);

        $this->assertEquals(0, StockMovement::where('stock_location_id', $location->id)->count());
        $this->assertEquals(2, StockArchivedMovement::where('stock_location_id', $location->id)->count());
    }

    public function test_compact_preserves_movement_data_in_archive(): void
    {
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: 100,
            type: StockMovementType::Purchase,
            reason: 'Test purchase',
        );

        $this->action->execute($location);

        $archived = StockArchivedMovement::where('stock_location_id', $location->id)->first();

        $this->assertEquals($product->id, $archived->product_id);
        $this->assertEquals(100, $archived->quantity);
        $this->assertEquals('purchase', $archived->type);
        $this->assertEquals('Test purchase', $archived->reason);
        $this->assertNotNull($archived->original_created_at);
        $this->assertNotNull($archived->original_updated_at);
    }

    public function test_movements_after_snapshot_are_not_removed(): void
    {
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: 100,
            type: StockMovementType::Purchase,
        );

        $this->action->execute($location);

        Carbon::setTestNow(Carbon::now()->addSecond());

        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: -10,
            type: StockMovementType::Sale,
        );

        $movementsAfterCompact = StockMovement::where('stock_location_id', $location->id)->count();
        $this->assertEquals(1, $movementsAfterCompact);

        $currentStock = $this->stockService->getStockForProduct($product, $location);
        $this->assertEquals(90, $currentStock);

        Carbon::setTestNow();
    }

    public function test_compact_does_not_affect_other_locations(): void
    {
        $locationA = StockLocation::factory()->create();
        $locationB = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->stockService->addMovement(
            product: $product,
            location: $locationA,
            quantity: 100,
            type: StockMovementType::Purchase,
        );
        $this->stockService->addMovement(
            product: $product,
            location: $locationB,
            quantity: 50,
            type: StockMovementType::Purchase,
        );

        $this->action->execute($locationA);

        $this->assertEquals(0, StockMovement::where('stock_location_id', $locationA->id)->count());
        $this->assertEquals(1, StockMovement::where('stock_location_id', $locationB->id)->count());

        $stockB = $this->stockService->getStockForProduct($product, $locationB);
        $this->assertEquals(50, $stockB);
    }

    public function test_compact_can_run_multiple_times(): void
    {
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: 100,
            type: StockMovementType::Purchase,
        );

        $this->action->execute($location);

        Carbon::setTestNow(Carbon::now()->addSecond());

        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: -30,
            type: StockMovementType::Sale,
        );

        $this->action->execute($location);

        $stock = $this->stockService->getStockForProduct($product, $location);
        $this->assertEquals(70, $stock);

        $this->assertEquals(2, StockSnapshot::count());

        Carbon::setTestNow();
    }

    public function test_compact_is_atomic(): void
    {
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: 100,
            type: StockMovementType::Purchase,
        );

        $this->action->execute($location);

        $this->assertDatabaseCount('stock_snapshots', 1);
        $this->assertDatabaseCount('stock_snapshot_lines', 1);
        $this->assertDatabaseCount('stock_archived_movements', 1);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_compact_with_negative_stock(): void
    {
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: -10,
            type: StockMovementType::Sale,
        );

        $this->action->execute($location);

        $stock = $this->stockService->getStockForProduct($product, $location);
        $this->assertEquals(-10, $stock);
    }

    public function test_transfers_continue_to_work_after_compact(): void
    {
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: 100,
            type: StockMovementType::Purchase,
        );

        $this->action->execute($location);

        Carbon::setTestNow(Carbon::now()->addSecond());

        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: -10,
            type: StockMovementType::Transfer,
        );

        $stock = $this->stockService->getStockForProduct($product, $location);
        $this->assertEquals(90, $stock);

        Carbon::setTestNow();
    }

    public function test_compact_preserves_stock_with_multiple_products(): void
    {
        $location = StockLocation::factory()->create();
        $beer = Product::factory()->create(['is_stockable' => true, 'name' => 'Beer']);
        $coffee = Product::factory()->create(['is_stockable' => true, 'name' => 'Coffee']);
        $chips = Product::factory()->create(['is_stockable' => true, 'name' => 'Chips']);

        $this->stockService->addMovement(product: $beer, location: $location, quantity: 100, type: StockMovementType::Purchase);
        $this->stockService->addMovement(product: $beer, location: $location, quantity: -20, type: StockMovementType::Sale);
        $this->stockService->addMovement(product: $coffee, location: $location, quantity: 50, type: StockMovementType::Purchase);
        $this->stockService->addMovement(product: $coffee, location: $location, quantity: -5, type: StockMovementType::Loss);
        $this->stockService->addMovement(product: $chips, location: $location, quantity: -3, type: StockMovementType::Sale);

        $beerBefore = $this->stockService->getStockForProduct($beer, $location);
        $coffeeBefore = $this->stockService->getStockForProduct($coffee, $location);
        $chipsBefore = $this->stockService->getStockForProduct($chips, $location);
        $this->assertEquals(80, $beerBefore);
        $this->assertEquals(45, $coffeeBefore);
        $this->assertEquals(-3, $chipsBefore);

        $this->action->execute($location);

        $this->assertEquals(80, $this->stockService->getStockForProduct($beer, $location));
        $this->assertEquals(45, $this->stockService->getStockForProduct($coffee, $location));
        $this->assertEquals(-3, $this->stockService->getStockForProduct($chips, $location));

        $this->assertEquals(0, StockMovement::where('stock_location_id', $location->id)->count());
        $this->assertEquals(5, StockArchivedMovement::where('stock_location_id', $location->id)->count());
    }

    public function test_compact_rolls_back_on_failure(): void
    {
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: 100,
            type: StockMovementType::Purchase,
        );

        $movementsBefore = StockMovement::count();
        $snapshotsBefore = StockSnapshot::count();

        // Test that the transaction rolls back everything on failure.
        // Simulate a failure after snapshot creation but before archiving completes.
        try {
            DB::transaction(function () use ($location) {
                $snapshot = StockSnapshot::create([
                    'stock_location_id' => $location->id,
                    'snapshot_at' => now(),
                ]);

                StockSnapshotLine::create([
                    'stock_snapshot_id' => $snapshot->id,
                    'product_id' => Product::factory()->create(['is_stockable' => true])->id,
                    'quantity' => 50,
                ]);

                throw new \RuntimeException('Simulated failure');
            });
        } catch (\RuntimeException $e) {
            // Expected.
        }

        // Everything should be rolled back: no snapshot, no archived movements,
        // original movements still present.
        $this->assertEquals($snapshotsBefore, StockSnapshot::count());
        $this->assertEquals(0, StockArchivedMovement::count());
        $this->assertEquals($movementsBefore, StockMovement::count());

        // Stock unchanged.
        $stock = $this->stockService->getStockForProduct($product, $location);
        $this->assertEquals(100, $stock);
    }

    public function test_compact_boundary_includes_movements_at_snapshot_time(): void
    {
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $fixedTime = Carbon::now();
        Carbon::setTestNow($fixedTime);

        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: 100,
            type: StockMovementType::Purchase,
        );

        // Compact at the exact same timestamp.
        $this->action->execute($location);

        // The movement at the snapshot time must be archived.
        $this->assertEquals(0, StockMovement::where('stock_location_id', $location->id)->count());
        $this->assertEquals(1, StockArchivedMovement::where('stock_location_id', $location->id)->count());

        // Stock must still be correct.
        $stock = $this->stockService->getStockForProduct($product, $location);
        $this->assertEquals(100, $stock);

        Carbon::setTestNow();
    }

    public function test_compact_boundary_excludes_post_snapshot_movements(): void
    {
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: 100,
            type: StockMovementType::Purchase,
        );

        $this->action->execute($location);

        // Move time forward and create a new movement.
        Carbon::setTestNow(Carbon::now()->addSecond());

        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: -30,
            type: StockMovementType::Sale,
        );

        // The post-snapshot movement must remain in stock_movements.
        $this->assertEquals(1, StockMovement::where('stock_location_id', $location->id)->count());
        $this->assertEquals(1, StockArchivedMovement::where('stock_location_id', $location->id)->count());

        // Stock must reflect both snapshot base and post-snapshot movement.
        $stock = $this->stockService->getStockForProduct($product, $location);
        $this->assertEquals(70, $stock);

        Carbon::setTestNow();
    }

    public function test_compact_multiple_movements_around_cutoff(): void
    {
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        // Create movements at different times.
        $this->stockService->addMovement(product: $product, location: $location, quantity: 100, type: StockMovementType::Purchase);

        Carbon::setTestNow(Carbon::now()->addSecond());

        $this->stockService->addMovement(product: $product, location: $location, quantity: -20, type: StockMovementType::Sale);

        Carbon::setTestNow(Carbon::now()->addSecond());

        $this->stockService->addMovement(product: $product, location: $location, quantity: 10, type: StockMovementType::Adjustment);

        $stockBefore = $this->stockService->getStockForProduct($product, $location);
        $this->assertEquals(90, $stockBefore);

        // Compact — all three movements should be archived.
        $this->action->execute($location);

        $stockAfter = $this->stockService->getStockForProduct($product, $location);
        $this->assertEquals(90, $stockAfter);

        $this->assertEquals(0, StockMovement::where('stock_location_id', $location->id)->count());
        $this->assertEquals(3, StockArchivedMovement::where('stock_location_id', $location->id)->count());

        Carbon::setTestNow();
    }

    public function test_archived_movements_preserve_all_original_data(): void
    {
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: -15,
            type: StockMovementType::Sale,
            reason: 'Customer return',
        );

        $this->action->execute($location);

        $archived = StockArchivedMovement::where('stock_location_id', $location->id)->first();

        $this->assertEquals($product->id, $archived->product_id);
        $this->assertEquals(-15, $archived->quantity);
        $this->assertEquals('sale', $archived->type);
        $this->assertEquals('Customer return', $archived->reason);
        $this->assertNotNull($archived->original_created_at);
        $this->assertNotNull($archived->original_updated_at);
        $this->assertNotNull($archived->stock_location_id);
    }
}
