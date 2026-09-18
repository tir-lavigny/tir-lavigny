<?php

namespace Tests\Unit\Actions\Stock;

use App\Actions\Stock\RecordStockLossAction;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Season;
use App\Models\StockLocation;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class RecordStockLossActionTest extends TestCase
{
    use RefreshDatabase;

    private RecordStockLossAction $action;

    private StockService $stockService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stockService = new StockService;
        $this->action = new RecordStockLossAction($this->stockService);
    }

    public function test_creates_negative_movement(): void
    {
        $season = Season::factory()->create();
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $movement = $this->action->execute(
            season: $season,
            location: $location,
            product: $product,
            quantity: 3,
            reason: 'Broken bottles',
        );

        $this->assertDatabaseHas('stock_movements', [
            'id' => $movement->id,
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'quantity' => -3,
        ]);
    }

    public function test_movement_type_is_loss(): void
    {
        $season = Season::factory()->create();
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $movement = $this->action->execute(
            season: $season,
            location: $location,
            product: $product,
            quantity: 2,
        );

        $this->assertEquals(StockMovementType::Loss, $movement->type);
    }

    public function test_reason_is_preserved(): void
    {
        $season = Season::factory()->create();
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $movement = $this->action->execute(
            season: $season,
            location: $location,
            product: $product,
            quantity: 2,
            reason: 'Spillage during transport',
        );

        $this->assertEquals('Spillage during transport', $movement->reason);
    }

    public function test_stockable_requirement_is_enforced(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('non-stockable');

        $season = Season::factory()->create();
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => false]);

        $this->action->execute(
            season: $season,
            location: $location,
            product: $product,
            quantity: 2,
        );
    }

    public function test_inactive_location_is_rejected(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('inactive');

        $season = Season::factory()->create();
        $location = StockLocation::factory()->inactive()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->action->execute(
            season: $season,
            location: $location,
            product: $product,
            quantity: 2,
        );
    }

    public function test_quantity_must_be_positive(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('greater than zero');

        $season = Season::factory()->create();
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->action->execute(
            season: $season,
            location: $location,
            product: $product,
            quantity: 0,
        );
    }

    public function test_loss_allowed_to_make_stock_negative(): void
    {
        $season = Season::factory()->create();
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->action->execute(
            season: $season,
            location: $location,
            product: $product,
            quantity: 3,
        );

        $stock = $this->stockService->getStockForProduct($product, $location);
        $this->assertEquals(-3, $stock);
    }

    public function test_season_is_attached(): void
    {
        $season = Season::factory()->create();
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $movement = $this->action->execute(
            season: $season,
            location: $location,
            product: $product,
            quantity: 2,
        );

        $this->assertEquals($season->id, $movement->season_id);
    }
}
