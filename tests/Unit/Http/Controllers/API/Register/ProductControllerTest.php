<?php

namespace Tests\Unit\Http\Controllers\API\Register;

use App\Http\Controllers\API\Register\ProductController;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private ProductController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->controller = new ProductController;
    }

    private function actingAsUser(User $user): void
    {
        $this->app['request']->setUserResolver(fn () => $user);
    }

    public function test_index_returns_products_from_unrestricted_categories(): void
    {
        $unrestricted = Category::factory()->create(['is_restricted' => false]);
        $restricted = Category::factory()->create(['is_restricted' => true]);

        Product::factory()->create(['name' => 'Bière', 'category_id' => $unrestricted->id]);
        Product::factory()->create(['name' => 'Jus', 'category_id' => $unrestricted->id]);
        Product::factory()->create(['name' => 'Munitions', 'category_id' => $restricted->id]);

        $this->actingAsUser(User::factory()->create());
        $response = $this->controller->index();
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->status());
        $this->assertCount(2, $data['data']);
    }

    public function test_index_includes_restricted_products_for_user_with_permission(): void
    {
        $restricted = Category::factory()->create(['is_restricted' => true]);
        Product::factory()->create(['name' => 'Munitions', 'category_id' => $restricted->id]);

        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAsUser($user);

        $response = $this->controller->index();
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->status());
        $this->assertCount(1, $data['data']);
        $this->assertEquals('Munitions', $data['data'][0]['name']);
    }

    public function test_index_excludes_soft_deleted_products(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        Product::factory()->create(['name' => 'Active', 'category_id' => $category->id]);
        Product::factory()->create(['name' => 'Deleted', 'category_id' => $category->id])->delete();

        $this->actingAsUser(User::factory()->create());
        $response = $this->controller->index();
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->status());
        $this->assertCount(1, $data['data']);
        $this->assertEquals('Active', $data['data'][0]['name']);
    }

    public function test_index_eager_loads_category_and_child_products(): void
    {
        $category = Category::factory()->create(['name' => 'Bar', 'is_restricted' => false]);
        $product = Product::factory()->create(['name' => 'Bière', 'category_id' => $category->id]);
        $child = Product::factory()->create(['name' => 'Schneider', 'category_id' => $category->id]);
        $product->childProducts()->attach($child->id, ['quantity' => 6]);

        $this->actingAsUser(User::factory()->create());
        $response = $this->controller->index();
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->status());
        $this->assertEquals('Bar', $data['data'][0]['category']['name']);
        $this->assertCount(1, $data['data'][0]['child_products']);
    }

    public function test_show_returns_single_product_with_relationships(): void
    {
        $category = Category::factory()->create(['name' => 'Bar', 'is_restricted' => false]);
        $product = Product::factory()->create(['name' => 'Bière', 'category_id' => $category->id]);
        $child = Product::factory()->create(['name' => 'Schneider', 'category_id' => $category->id]);
        $product->childProducts()->attach($child->id, ['quantity' => 6]);

        $response = $this->controller->show($product);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->status());
        $this->assertEquals('Bière', $data['data']['name']);
        $this->assertEquals('Bar', $data['data']['category']['name']);
        $this->assertCount(1, $data['data']['child_products']);
    }
}
