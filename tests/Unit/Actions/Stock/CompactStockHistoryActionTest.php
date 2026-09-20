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
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
