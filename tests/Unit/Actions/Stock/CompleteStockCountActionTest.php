<?php

namespace Tests\Unit\Actions\Stock;

use App\Actions\Stock\CompleteStockCountAction;
use App\Enums\StockCountType;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Season;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class CompleteStockCountActionTest extends TestCase
{
    use RefreshDatabase;

    private CompleteStockCountAction $action;

    private StockService $stockService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stockService = new StockService;
        $this->action = new CompleteStockCountAction($this->stockService);
    }

    private function createOpenCount(StockLocation $location, Season $season): StockCount
    {
        return StockCount::create([
            'season_id' => $season->id,
            'stock_location_id' => $location->id,
            'type' => StockCountType::Adjustment,
            'status' => 'pending',
        ]);
    }

    public function test_positive_difference_creates_positive_adjustment(): void
    {
        $season = Season::factory()->create();
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $count = $this->createOpenCount($location, $season);

        StockCountLine::create([
            'stock_count_id' => $count->id,
            'product_id' => $product->id,
            'expected_quantity' => 10,
            'counted_quantity' => 15,
        ]);

        $this->action->execute($count);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'quantity' => 5,
            'type' => StockMovementType::Adjustment,
            'reference_type' => StockCount::class,
            'reference_id' => $count->id,
        ]);
    }

    public function test_negative_difference_creates_negative_adjustment(): void
    {
        $season = Season::factory()->create();
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $count = $this->createOpenCount($location, $season);

        StockCountLine::create([
            'stock_count_id' => $count->id,
            'product_id' => $product->id,
            'expected_quantity' => 10,
            'counted_quantity' => 7,
        ]);

        $this->action->execute($count);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'quantity' => -3,
            'type' => StockMovementType::Adjustment,
        ]);
    }

    public function test_zero_difference_creates_no_movement(): void
    {
        $season = Season::factory()->create();
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $count = $this->createOpenCount($location, $season);

        StockCountLine::create([
            'stock_count_id' => $count->id,
            'product_id' => $product->id,
            'expected_quantity' => 10,
            'counted_quantity' => 10,
        ]);

        $this->action->execute($count);

        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_movement_references_the_stock_count(): void
    {
        $season = Season::factory()->create();
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $count = $this->createOpenCount($location, $season);

        StockCountLine::create([
            'stock_count_id' => $count->id,
            'product_id' => $product->id,
            'expected_quantity' => 5,
            'counted_quantity' => 8,
        ]);

        $this->action->execute($count);

        $movement = StockMovement::where('reference_type', StockCount::class)
            ->where('reference_id', $count->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals($location->id, $movement->stock_location_id);
        $this->assertEquals($season->id, $movement->season_id);
    }

    public function test_count_becomes_completed(): void
    {
        $season = Season::factory()->create();
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $count = $this->createOpenCount($location, $season);

        StockCountLine::create([
            'stock_count_id' => $count->id,
            'product_id' => $product->id,
            'expected_quantity' => 10,
            'counted_quantity' => 10,
        ]);

        $this->action->execute($count);

        $count->refresh();
        $this->assertEquals('completed', $count->status);
        $this->assertNotNull($count->completed_at);
    }

    public function test_completed_count_cannot_be_completed_again(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('already been completed');

        $season = Season::factory()->create();
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $count = StockCount::factory()->completed()->create([
            'season_id' => $season->id,
            'stock_location_id' => $location->id,
        ]);

        StockCountLine::create([
            'stock_count_id' => $count->id,
            'product_id' => $product->id,
            'expected_quantity' => 10,
            'counted_quantity' => 10,
        ]);

        $this->action->execute($count);
    }

    public function test_all_lines_must_be_counted(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('has not been counted yet');

        $season = Season::factory()->create();
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $count = $this->createOpenCount($location, $season);

        StockCountLine::create([
            'stock_count_id' => $count->id,
            'product_id' => $product->id,
            'expected_quantity' => 10,
            'counted_quantity' => null,
        ]);

        $this->action->execute($count);
    }

    public function test_existing_movements_remain_untouched(): void
    {
        $season = Season::factory()->create();
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->stockService->addMovement(
            product: $product,
            location: $location,
            quantity: 20,
            type: StockMovementType::Purchase,
            season: $season,
        );

        $count = $this->createOpenCount($location, $season);

        StockCountLine::create([
            'stock_count_id' => $count->id,
            'product_id' => $product->id,
            'expected_quantity' => 20,
            'counted_quantity' => 18,
        ]);

        $this->action->execute($count);

        $purchaseMovement = StockMovement::where('product_id', $product->id)
            ->where('stock_location_id', $location->id)
            ->where('type', StockMovementType::Purchase)
            ->first();

        $this->assertEquals(20, $purchaseMovement->quantity);
    }

    public function test_zero_counted_quantity_creates_adjustment(): void
    {
        $season = Season::factory()->create();
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $count = $this->createOpenCount($location, $season);

        StockCountLine::create([
            'stock_count_id' => $count->id,
            'product_id' => $product->id,
            'expected_quantity' => 10,
            'counted_quantity' => 0,
        ]);

        $this->action->execute($count);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'quantity' => -10,
            'type' => StockMovementType::Adjustment,
        ]);
    }

    public function test_negative_expected_and_counted_quantities(): void
    {
        $season = Season::factory()->create();
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $count = $this->createOpenCount($location, $season);

        StockCountLine::create([
            'stock_count_id' => $count->id,
            'product_id' => $product->id,
            'expected_quantity' => -3,
            'counted_quantity' => -2,
        ]);

        $this->action->execute($count);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'quantity' => 1,
            'type' => StockMovementType::Adjustment,
        ]);

        $this->assertEquals(1, StockMovement::where('product_id', $product->id)
            ->where('stock_location_id', $location->id)
            ->where('type', StockMovementType::Adjustment)
            ->sum('quantity'));
    }
}
