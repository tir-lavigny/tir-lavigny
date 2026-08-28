<?php

namespace Tests\Unit\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\BackOffice\Register\ProductController;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Response;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private ProductController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new ProductController;
    }

    public function test_index_returns_inertia_response(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        Product::factory()->count(3)->create(['category_id' => $category->id]);

        $response = $this->controller->index(Request::create('/'));

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_index_with_search_returns_inertia_response(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        Product::factory()->create(['name' => 'Bière', 'category_id' => $category->id]);
        Product::factory()->create(['name' => 'Jus', 'category_id' => $category->id]);

        $request = Request::create('/?search=Bière');
        $response = $this->controller->index($request);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_show_returns_inertia_response(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $response = $this->controller->show($product);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_product_model_can_be_created(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);

        $product = Product::create([
            'name' => 'Jus de pomme',
            'unit_price' => 4.50,
            'allows_custom_price' => false,
            'category_id' => $category->id,
        ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Jus de pomme',
            'unit_price' => 4.50,
        ]);
    }

    public function test_product_model_can_be_updated(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create([
            'name' => 'Old',
            'category_id' => $category->id,
        ]);

        $product->update(['name' => 'New']);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'New']);
    }

    public function test_product_model_soft_deletes(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $product->delete();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_attach_child_creates_pivot(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $parent = Product::factory()->create(['category_id' => $category->id]);
        $child = Product::factory()->create(['category_id' => $category->id]);

        $parent->childProducts()->attach($child->id, ['quantity' => 6]);

        $this->assertDatabaseHas('product_product', [
            'product_id' => $parent->id,
            'child_product_id' => $child->id,
            'quantity' => 6,
        ]);
    }

    public function test_detach_child_removes_pivot(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $parent = Product::factory()->create(['category_id' => $category->id]);
        $child = Product::factory()->create(['category_id' => $category->id]);
        $parent->childProducts()->attach($child->id, ['quantity' => 6]);

        $parent->childProducts()->detach($child->id);

        $this->assertDatabaseMissing('product_product', [
            'product_id' => $parent->id,
            'child_product_id' => $child->id,
        ]);
    }
}
