<?php

namespace Tests\Unit\Actions\Stock;

use App\Actions\Stock\CreateStockSnapshotAction;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\StockSnapshot;
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateStockSnapshotActionTest extends TestCase
{
    use RefreshDatabase;

    private CreateStockSnapshotAction $action;
    private StockService $stockService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stockService = new StockService;
        $this->action = new CreateStockSnapshotAction($this->stockService);
    }

    public function test_creates_snapshot(): void
    {
        $location = StockLocation::factory()->create();

        $snapshot = $this->action->execute($location);

        $this->assertInstanceOf(StockSnapshot::class, $snapshot);
        $this->assertDatabaseHas('stock_snapshots', [
            'id' => $snapshot->id,
            'stock_location_id' => $location->id,
        ]);
    }

    public function test_snapshot_captures_current_stock(): void
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

        $snapshot = $this->action->execute($location);

        $line = $snapshot->stockSnapshotLines->firstWhere('product_id', $product->id);
        $this->assertNotNull($line);
        $this->assertEquals(80, $line->quantity);
    }

    public function test_snapshot_includes_all_stockable_products_including_zero(): void
    {
        $location = StockLocation::factory()->create();
        $beer = Product::factory()->create(['is_stockable' => true, 'name' => 'Beer']);
        $coffee = Product::factory()->create(['is_stockable' => true, 'name' => 'Coffee']);

        $this->stockService->addMovement(
            product: $beer,
            location: $location,
            quantity: 50,
            type: StockMovementType::Purchase,
        );

        $snapshot = $this->action->execute($location);

        $this->assertCount(2, $snapshot->stockSnapshotLines);

        $beerLine = $snapshot->stockSnapshotLines->firstWhere('product_id', $beer->id);
        $this->assertEquals(50, $beerLine->quantity);

        $coffeeLine = $snapshot->stockSnapshotLines->firstWhere('product_id', $coffee->id);
        $this->assertEquals(0, $coffeeLine->quantity);
    }

    public function test_snapshot_captures_negative_stock(): void
    {
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: -10,
            type: StockMovementType::Sale,
        );

        $snapshot = $this->action->execute($location);

        $line = $snapshot->stockSnapshotLines->firstWhere('product_id', $product->id);
        $this->assertEquals(-10, $line->quantity);
    }

    public function test_snapshot_creates_no_stock_movements(): void
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

        $this->action->execute($location);

        $this->assertEquals($movementsBefore, StockMovement::count());
    }

    public function test_stock_after_snapshot_equals_snapshot_plus_post_movements(): void
    {
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: 100,
            type: StockMovementType::Purchase,
        );

        $snapshot = $this->action->execute($location);

        Carbon::setTestNow(Carbon::now()->addSecond());

        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: -10,
            type: StockMovementType::Sale,
        );
        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: 20,
            type: StockMovementType::Transfer,
        );

        $currentStock = $this->stockService->getStockForProduct($product, $location);
        $this->assertEquals(110, $currentStock);

        Carbon::setTestNow();
    }

    public function test_multiple_snapshots_use_latest_as_base(): void
    {
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: 100,
            type: StockMovementType::Purchase,
        );

        $firstSnapshot = $this->action->execute($location);

        Carbon::setTestNow(Carbon::now()->addSecond());

        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: -20,
            type: StockMovementType::Sale,
        );

        $secondSnapshot = $this->action->execute($location);

        $secondLine = $secondSnapshot->stockSnapshotLines->firstWhere('product_id', $product->id);
        $this->assertEquals(80, $secondLine->quantity);

        Carbon::setTestNow(Carbon::now()->addSecond());

        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: 10,
            type: StockMovementType::Transfer,
        );

        $currentStock = $this->stockService->getStockForProduct($product, $location);
        $this->assertEquals(90, $currentStock);

        Carbon::setTestNow();
    }

    public function test_old_movements_not_double_counted(): void
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

    public function test_snapshot_is_atomic(): void
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

        $this->assertDatabaseHas('stock_snapshots', ['id' => $snapshot->id]);
        $this->assertDatabaseCount('stock_snapshot_lines', 1);
    }

    public function test_location_without_movements_creates_empty_snapshot(): void
    {
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $snapshot = $this->action->execute($location);

        $line = $snapshot->stockSnapshotLines->firstWhere('product_id', $product->id);
        $this->assertNotNull($line);
        $this->assertEquals(0, $line->quantity);
    }

    public function test_snapshot_sets_snapshot_at_consistently(): void
    {
        $location = StockLocation::factory()->create();
        $before = now()->subSecond();

        $snapshot = $this->action->execute($location);

        $after = now()->addSecond();
        $this->assertTrue($snapshot->snapshot_at->gte($before));
        $this->assertTrue($snapshot->snapshot_at->lte($after));
    }

    public function test_multiple_locations_have_independent_snapshots(): void
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

        $snapshotA = $this->action->execute($locationA);
        $snapshotB = $this->action->execute($locationB);

        $lineA = $snapshotA->stockSnapshotLines->firstWhere('product_id', $product->id);
        $lineB = $snapshotB->stockSnapshotLines->firstWhere('product_id', $product->id);

        $this->assertEquals(100, $lineA->quantity);
        $this->assertEquals(50, $lineB->quantity);
    }

    public function test_location_without_snapshot_falls_back_to_movement_calculation(): void
    {
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: 75,
            type: StockMovementType::Purchase,
        );

        $stock = $this->stockService->getStockForProduct($product, $location);
        $this->assertEquals(75, $stock);
    }
}
