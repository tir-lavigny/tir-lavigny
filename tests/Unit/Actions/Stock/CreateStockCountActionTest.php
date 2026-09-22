<?php

namespace Tests\Unit\Actions\Stock;

use App\Actions\Stock\CreateStockCountAction;
use App\Enums\StockCountType;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Season;
use App\Models\StockCountLine;
use App\Models\StockLocation;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class CreateStockCountActionTest extends TestCase
{
    use RefreshDatabase;

    private CreateStockCountAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CreateStockCountAction(new StockService);
    }

    public function test_creates_a_stock_count(): void
    {
        $season = Season::factory()->create();
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $count = $this->action->execute(
            season: $season,
            location: $location,
            type: StockCountType::Initial,
            products: collect([$product]),
        );

        $this->assertDatabaseHas('stock_counts', [
            'id' => $count->id,
            'season_id' => $season->id,
            'stock_location_id' => $location->id,
            'type' => StockCountType::Initial,
            'status' => 'pending',
            'completed_at' => null,
        ]);
    }

    public function test_creates_lines_for_stockable_products(): void
    {
        $season = Season::factory()->create();
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $count = $this->action->execute(
            season: $season,
            location: $location,
            type: StockCountType::Initial,
            products: collect([$product]),
        );

        $this->assertDatabaseHas('stock_count_lines', [
            'stock_count_id' => $count->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_expected_quantity_calculated_from_ledger(): void
    {
        $season = Season::factory()->create();
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $stockService = new StockService;
        $stockService->addMovement(
            product: $product,
            location: $location,
            quantity: 25,
            type: StockMovementType::Purchase,
            season: $season,
        );
        $stockService->addMovement(
            product: $product,
            location: $location,
            quantity: -5,
            type: StockMovementType::Sale,
            season: $season,
        );

        $count = $this->action->execute(
            season: $season,
            location: $location,
            type: StockCountType::Adjustment,
            products: collect([$product]),
        );

        $line = StockCountLine::where('stock_count_id', $count->id)
            ->where('product_id', $product->id)
            ->first();

        $this->assertEquals(20, $line->expected_quantity);
    }

    public function test_counted_quantity_starts_as_null(): void
    {
        $season = Season::factory()->create();
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $count = $this->action->execute(
            season: $season,
            location: $location,
            type: StockCountType::Initial,
            products: collect([$product]),
        );

        $line = StockCountLine::where('stock_count_id', $count->id)
            ->where('product_id', $product->id)
            ->first();

        $this->assertNull($line->counted_quantity);
    }

    public function test_non_stockable_products_are_excluded(): void
    {
        $season = Season::factory()->create();
        $location = StockLocation::factory()->create();
        $stockable = Product::factory()->create(['is_stockable' => true]);
        $nonStockable = Product::factory()->create(['is_stockable' => false]);

        $count = $this->action->execute(
            season: $season,
            location: $location,
            type: StockCountType::Initial,
            products: collect([$stockable, $nonStockable]),
        );

        $this->assertDatabaseHas('stock_count_lines', [
            'stock_count_id' => $count->id,
            'product_id' => $stockable->id,
        ]);

        $this->assertDatabaseCount('stock_count_lines', 1);
    }

    public function test_inactive_location_is_rejected(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('inactive location');

        $season = Season::factory()->create();
        $location = StockLocation::factory()->inactive()->create();
        $product = Product::factory()->create();

        $this->action->execute(
            season: $season,
            location: $location,
            type: StockCountType::Initial,
            products: collect([$product]),
        );
    }

    public function test_does_not_create_adjustment_movements(): void
    {
        $season = Season::factory()->create();
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->action->execute(
            season: $season,
            location: $location,
            type: StockCountType::Initial,
            products: collect([$product]),
        );

        $this->assertDatabaseCount('stock_movements', 0);
    }
}
