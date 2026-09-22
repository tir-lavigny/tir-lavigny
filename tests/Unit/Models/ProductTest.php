<?php

namespace Tests\Unit\Models;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_can_have_child_products(): void
    {
        $parent = Product::factory()->create();
        $child1 = Product::factory()->create();
        $child2 = Product::factory()->create();

        $parent->childProducts()->attach($child1, ['quantity' => 2]);
        $parent->childProducts()->attach($child2, ['quantity' => 1]);

        $this->assertCount(2, $parent->childProducts);
        $this->assertEquals(2, $parent->childProducts->first()->pivot->quantity);
    }

    public function test_product_can_be_child_of_multiple_parents(): void
    {
        $child = Product::factory()->create();
        $parent1 = Product::factory()->create();
        $parent2 = Product::factory()->create();

        $parent1->childProducts()->attach($child, ['quantity' => 1]);
        $parent2->childProducts()->attach($child, ['quantity' => 3]);

        $this->assertCount(2, $child->parentProducts);
    }

    public function test_parent_products_returns_correct_direction(): void
    {
        $parent = Product::factory()->create();
        $child = Product::factory()->create();

        $parent->childProducts()->attach($child, ['quantity' => 1]);

        $this->assertCount(1, $parent->childProducts);
        $this->assertCount(1, $child->parentProducts);
        $this->assertEquals($parent->id, $child->parentProducts->first()->id);
    }

    public function test_product_belongs_to_category(): void
    {
        $product = Product::factory()->create();

        $this->assertNotNull($product->category);
    }

    public function test_changing_is_stockable_does_not_corrupt_existing_movements(): void
    {
        $product = Product::factory()->create(['is_stockable' => true]);
        $location = StockLocation::factory()->create();
        $stockService = new StockService;

        $stockService->addMovement(
            product: $product,
            location: $location,
            quantity: 50,
            type: StockMovementType::Purchase,
        );
        $stockService->addMovement(
            product: $product,
            location: $location,
            quantity: -10,
            type: StockMovementType::Sale,
        );

        $this->assertEquals(40, $stockService->getStockForProduct($product, $location));

        $movementCount = StockMovement::where('product_id', $product->id)->count();
        $this->assertEquals(2, $movementCount);

        $product->update(['is_stockable' => false]);

        $this->assertEquals(40, $stockService->getStockForProduct($product, $location));
        $this->assertEquals($movementCount, StockMovement::where('product_id', $product->id)->count());
    }
}
