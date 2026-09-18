<?php

namespace Tests\Unit\Actions\Stock;

use App\Actions\Stock\CreateStockTransferAction;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Season;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class CreateStockTransferActionTest extends TestCase
{
    use RefreshDatabase;

    private CreateStockTransferAction $action;

    private StockService $stockService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stockService = new StockService;
        $this->action = new CreateStockTransferAction($this->stockService);
    }

    public function test_creates_transfer(): void
    {
        $season = Season::factory()->create();
        $from = StockLocation::factory()->create();
        $to = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $transfer = $this->action->execute(
            season: $season,
            fromLocation: $from,
            toLocation: $to,
            items: collect([['product_id' => $product->id, 'quantity' => 10]]),
            reason: 'Restock',
        );

        $this->assertDatabaseHas('stock_transfers', [
            'id' => $transfer->id,
            'from_location_id' => $from->id,
            'to_location_id' => $to->id,
            'season_id' => $season->id,
            'reason' => 'Restock',
        ]);
    }

    public function test_creates_transfer_lines(): void
    {
        $season = Season::factory()->create();
        $from = StockLocation::factory()->create();
        $to = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $transfer = $this->action->execute(
            season: $season,
            fromLocation: $from,
            toLocation: $to,
            items: collect([['product_id' => $product->id, 'quantity' => 10]]),
        );

        $this->assertDatabaseHas('stock_transfer_lines', [
            'stock_transfer_id' => $transfer->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);
    }

    public function test_source_receives_negative_movement(): void
    {
        $season = Season::factory()->create();
        $from = StockLocation::factory()->create();
        $to = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->action->execute(
            season: $season,
            fromLocation: $from,
            toLocation: $to,
            items: collect([['product_id' => $product->id, 'quantity' => 10]]),
        );

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'stock_location_id' => $from->id,
            'quantity' => -10,
            'type' => StockMovementType::Transfer,
        ]);
    }

    public function test_destination_receives_positive_movement(): void
    {
        $season = Season::factory()->create();
        $from = StockLocation::factory()->create();
        $to = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->action->execute(
            season: $season,
            fromLocation: $from,
            toLocation: $to,
            items: collect([['product_id' => $product->id, 'quantity' => 10]]),
        );

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'stock_location_id' => $to->id,
            'quantity' => 10,
            'type' => StockMovementType::Transfer,
        ]);
    }

    public function test_both_movements_reference_same_transfer(): void
    {
        $season = Season::factory()->create();
        $from = StockLocation::factory()->create();
        $to = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $transfer = $this->action->execute(
            season: $season,
            fromLocation: $from,
            toLocation: $to,
            items: collect([['product_id' => $product->id, 'quantity' => 10]]),
        );

        $movements = StockMovement::where('reference_type', StockTransfer::class)
            ->where('reference_id', $transfer->id)
            ->get();

        $this->assertCount(2, $movements);
        $this->assertTrue($movements->contains('stock_location_id', $from->id));
        $this->assertTrue($movements->contains('stock_location_id', $to->id));
    }

    public function test_movement_type_is_transfer(): void
    {
        $season = Season::factory()->create();
        $from = StockLocation::factory()->create();
        $to = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->action->execute(
            season: $season,
            fromLocation: $from,
            toLocation: $to,
            items: collect([['product_id' => $product->id, 'quantity' => 10]]),
        );

        $movements = StockMovement::where('product_id', $product->id)->get();

        $movements->each(function (StockMovement $m) {
            $this->assertEquals(StockMovementType::Transfer, $m->type);
        });
    }

    public function test_source_stock_allowed_to_become_negative(): void
    {
        $season = Season::factory()->create();
        $from = StockLocation::factory()->create();
        $to = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->stockService->addMovement(
            product: $product,
            location: $from,
            quantity: 2,
            type: StockMovementType::Purchase,
            season: $season,
        );

        $this->action->execute(
            season: $season,
            fromLocation: $from,
            toLocation: $to,
            items: collect([['product_id' => $product->id, 'quantity' => 5]]),
        );

        $stock = $this->stockService->getStockForProduct($product, $from);
        $this->assertEquals(-3, $stock);
    }

    public function test_transfer_succeeds_even_when_source_has_insufficient_recorded_stock(): void
    {
        $season = Season::factory()->create();
        $from = StockLocation::factory()->create();
        $to = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $transfer = $this->action->execute(
            season: $season,
            fromLocation: $from,
            toLocation: $to,
            items: collect([['product_id' => $product->id, 'quantity' => 5]]),
        );

        $this->assertNotNull($transfer->id);
        $this->assertEquals(-5, $this->stockService->getStockForProduct($product, $from));
        $this->assertEquals(5, $this->stockService->getStockForProduct($product, $to));
    }

    public function test_source_and_destination_cannot_be_the_same(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('different');

        $season = Season::factory()->create();
        $location = StockLocation::factory()->create();
        $product = Product::factory()->create();

        $this->action->execute(
            season: $season,
            fromLocation: $location,
            toLocation: $location,
            items: collect([['product_id' => $product->id, 'quantity' => 5]]),
        );
    }

    public function test_inactive_source_location_is_rejected(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('inactive');

        $season = Season::factory()->create();
        $from = StockLocation::factory()->inactive()->create();
        $to = StockLocation::factory()->create();
        $product = Product::factory()->create();

        $this->action->execute(
            season: $season,
            fromLocation: $from,
            toLocation: $to,
            items: collect([['product_id' => $product->id, 'quantity' => 5]]),
        );
    }

    public function test_inactive_destination_location_is_rejected(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('inactive');

        $season = Season::factory()->create();
        $from = StockLocation::factory()->create();
        $to = StockLocation::factory()->inactive()->create();
        $product = Product::factory()->create();

        $this->action->execute(
            season: $season,
            fromLocation: $from,
            toLocation: $to,
            items: collect([['product_id' => $product->id, 'quantity' => 5]]),
        );
    }

    public function test_non_stockable_product_is_rejected(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('not stockable');

        $season = Season::factory()->create();
        $from = StockLocation::factory()->create();
        $to = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => false]);

        $this->action->execute(
            season: $season,
            fromLocation: $from,
            toLocation: $to,
            items: collect([['product_id' => $product->id, 'quantity' => 5]]),
        );
    }

    public function test_invalid_transfer_does_not_partially_mutate_ledger(): void
    {
        $season = Season::factory()->create();
        $from = StockLocation::factory()->create();
        $to = StockLocation::factory()->create();
        $validProduct = Product::factory()->create(['is_stockable' => true]);
        $nonStockableProduct = Product::factory()->create(['is_stockable' => false]);

        $movementsBefore = StockMovement::count();

        try {
            $this->action->execute(
                season: $season,
                fromLocation: $from,
                toLocation: $to,
                items: collect([
                    ['product_id' => $validProduct->id, 'quantity' => 10],
                    ['product_id' => $nonStockableProduct->id, 'quantity' => 5],
                ]),
            );
            $this->fail('Expected LogicException for non-stockable product');
        } catch (LogicException $e) {
            $this->assertStringContainsString('not stockable', $e->getMessage());
        }

        $this->assertEquals($movementsBefore, StockMovement::count());
        $this->assertDatabaseCount('stock_transfers', 0);
    }

    public function test_transfer_with_multiple_products(): void
    {
        $season = Season::factory()->create();
        $from = StockLocation::factory()->create();
        $to = StockLocation::factory()->create();
        $beer = Product::factory()->create(['is_stockable' => true]);
        $coffee = Product::factory()->create(['is_stockable' => true]);

        $transfer = $this->action->execute(
            season: $season,
            fromLocation: $from,
            toLocation: $to,
            items: collect([
                ['product_id' => $beer->id, 'quantity' => 20],
                ['product_id' => $coffee->id, 'quantity' => 10],
            ]),
            reason: 'Restock bar',
        );

        $this->assertDatabaseCount('stock_transfer_lines', 2);
        $this->assertDatabaseCount('stock_movements', 4);

        $this->assertEquals(-20, $this->stockService->getStockForProduct($beer, $from));
        $this->assertEquals(20, $this->stockService->getStockForProduct($beer, $to));
        $this->assertEquals(-10, $this->stockService->getStockForProduct($coffee, $from));
        $this->assertEquals(10, $this->stockService->getStockForProduct($coffee, $to));
    }

    public function test_duplicate_products_are_rejected(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('duplicate');

        $season = Season::factory()->create();
        $from = StockLocation::factory()->create();
        $to = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->action->execute(
            season: $season,
            fromLocation: $from,
            toLocation: $to,
            items: collect([
                ['product_id' => $product->id, 'quantity' => 5],
                ['product_id' => $product->id, 'quantity' => 3],
            ]),
        );
    }

    public function test_quantity_must_be_greater_than_zero(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('greater than zero');

        $season = Season::factory()->create();
        $from = StockLocation::factory()->create();
        $to = StockLocation::factory()->create();
        $product = Product::factory()->create(['is_stockable' => true]);

        $this->action->execute(
            season: $season,
            fromLocation: $from,
            toLocation: $to,
            items: collect([['product_id' => $product->id, 'quantity' => 0]]),
        );
    }
}
