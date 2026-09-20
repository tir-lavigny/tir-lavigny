<?php

namespace Tests\Unit\Services;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Season;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\StockSnapshot;
use App\Models\StockSnapshotLine;
use App\Models\Ticket;
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class StockServiceSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private StockService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StockService;
    }

    public function test_get_stock_for_product_without_snapshot(): void
    {
        $product = Product::factory()->create();
        $location = StockLocation::factory()->create();

        $this->service->addMovement(
            product: $product,
            location: $location,
            quantity: 50,
            type: StockMovementType::Purchase,
        );

        $this->assertEquals(50, $this->service->getStockForProduct($product, $location));
    }

    public function test_get_stock_for_product_with_snapshot(): void
    {
        $product = Product::factory()->create();
        $location = StockLocation::factory()->create();

        $this->service->addMovement(
            product: $product,
            location: $location,
            quantity: 100,
            type: StockMovementType::Purchase,
        );

        $snapshot = StockSnapshot::create([
            'stock_location_id' => $location->id,
            'snapshot_at' => now()->addSecond(),
        ]);
        StockSnapshotLine::create([
            'stock_snapshot_id' => $snapshot->id,
            'product_id' => $product->id,
            'quantity' => 100,
        ]);

        $this->assertEquals(100, $this->service->getStockForProduct($product, $location));
    }

    public function test_get_stock_for_product_snapshot_plus_post_movements(): void
    {
        $product = Product::factory()->create();
        $location = StockLocation::factory()->create();

        $this->service->addMovement(
            product: $product,
            location: $location,
            quantity: 100,
            type: StockMovementType::Purchase,
        );

        $snapshot = StockSnapshot::create([
            'stock_location_id' => $location->id,
            'snapshot_at' => now(),
        ]);
        StockSnapshotLine::create([
            'stock_snapshot_id' => $snapshot->id,
            'product_id' => $product->id,
            'quantity' => 100,
        ]);

        Carbon::setTestNow(Carbon::now()->addSecond());

        $this->service->addMovement(
            product: $product,
            location: $location,
            quantity: -10,
            type: StockMovementType::Sale,
        );

        $this->assertEquals(90, $this->service->getStockForProduct($product, $location));

        Carbon::setTestNow();
    }

    public function test_get_stock_for_location_without_snapshot(): void
    {
        $location = StockLocation::factory()->create();
        $beer = Product::factory()->create(['is_stockable' => true, 'name' => 'Beer']);
        $coffee = Product::factory()->create(['is_stockable' => true, 'name' => 'Coffee']);

        $this->service->addMovement(
            product: $beer,
            location: $location,
            quantity: 50,
            type: StockMovementType::Purchase,
        );

        $stock = $this->service->getStockForLocation($location);

        $this->assertCount(2, $stock);
        $beerStock = $stock->firstWhere('product_id', $beer->id);
        $this->assertEquals(50, $beerStock['quantity']);
        $coffeeStock = $stock->firstWhere('product_id', $coffee->id);
        $this->assertEquals(0, $coffeeStock['quantity']);
    }

    public function test_get_stock_for_location_with_snapshot(): void
    {
        $location = StockLocation::factory()->create();
        $beer = Product::factory()->create(['is_stockable' => true, 'name' => 'Beer']);
        $coffee = Product::factory()->create(['is_stockable' => true, 'name' => 'Coffee']);

        $this->service->addMovement(
            product: $beer,
            location: $location,
            quantity: 100,
            type: StockMovementType::Purchase,
        );
        $this->service->addMovement(
            product: $coffee,
            location: $location,
            quantity: 50,
            type: StockMovementType::Purchase,
        );

        $snapshot = StockSnapshot::create([
            'stock_location_id' => $location->id,
            'snapshot_at' => now()->addSecond(),
        ]);
        StockSnapshotLine::create([
            'stock_snapshot_id' => $snapshot->id,
            'product_id' => $beer->id,
            'quantity' => 100,
        ]);
        StockSnapshotLine::create([
            'stock_snapshot_id' => $snapshot->id,
            'product_id' => $coffee->id,
            'quantity' => 50,
        ]);

        $stock = $this->service->getStockForLocation($location);

        $beerStock = $stock->firstWhere('product_id', $beer->id);
        $this->assertEquals(100, $beerStock['quantity']);
        $coffeeStock = $stock->firstWhere('product_id', $coffee->id);
        $this->assertEquals(50, $coffeeStock['quantity']);
    }

    public function test_latest_snapshot_is_used(): void
    {
        $product = Product::factory()->create();
        $location = StockLocation::factory()->create();

        $this->service->addMovement(
            product: $product,
            location: $location,
            quantity: 100,
            type: StockMovementType::Purchase,
        );

        $oldSnapshot = StockSnapshot::create([
            'stock_location_id' => $location->id,
            'snapshot_at' => now()->subHour(),
        ]);
        StockSnapshotLine::create([
            'stock_snapshot_id' => $oldSnapshot->id,
            'product_id' => $product->id,
            'quantity' => 100,
        ]);

        Carbon::setTestNow(Carbon::now()->addSecond());

        $this->service->addMovement(
            product: $product,
            location: $location,
            quantity: -20,
            type: StockMovementType::Sale,
        );

        Carbon::setTestNow(Carbon::now()->addSecond());

        $newSnapshot = StockSnapshot::create([
            'stock_location_id' => $location->id,
            'snapshot_at' => now(),
        ]);
        StockSnapshotLine::create([
            'stock_snapshot_id' => $newSnapshot->id,
            'product_id' => $product->id,
            'quantity' => 80,
        ]);

        Carbon::setTestNow(Carbon::now()->addSecond());

        $this->service->addMovement(
            product: $product,
            location: $location,
            quantity: 10,
            type: StockMovementType::Transfer,
        );

        $this->assertEquals(90, $this->service->getStockForProduct($product, $location));

        Carbon::setTestNow();
    }

    public function test_product_without_snapshot_line_uses_zero_base(): void
    {
        $product = Product::factory()->create();
        $location = StockLocation::factory()->create();

        $snapshot = StockSnapshot::create([
            'stock_location_id' => $location->id,
            'snapshot_at' => now()->subHour(),
        ]);

        $this->service->addMovement(
            product: $product,
            location: $location,
            quantity: 30,
            type: StockMovementType::Purchase,
        );

        $this->assertEquals(30, $this->service->getStockForProduct($product, $location));
    }
}
