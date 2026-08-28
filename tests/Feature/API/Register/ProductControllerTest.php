<?php

namespace Tests\Feature\API\Register;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\API\AuthTestHelper;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private array $tokens;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('cashier');
        $this->tokens = AuthTestHelper::generateTokens($this->user);
    }

    protected function tearDown(): void
    {
        AuthTestHelper::clearUser($this->user);
        parent::tearDown();
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->tokens['accessToken']];
    }

    public function test_index_returns_products_from_unrestricted_categories(): void
    {
        $unrestricted = Category::factory()->create(['is_restricted' => false]);
        $restricted = Category::factory()->create(['is_restricted' => true]);

        Product::factory()->create(['name' => 'Bière', 'category_id' => $unrestricted->id]);
        Product::factory()->create(['name' => 'Jus', 'category_id' => $unrestricted->id]);
        Product::factory()->create(['name' => 'Munitions', 'category_id' => $restricted->id]);

        $response = $this->getJson(route('api.register.products.index'), $this->authHeaders());

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_index_includes_restricted_products_for_user_with_permission(): void
    {
        $restricted = Category::factory()->create(['is_restricted' => true]);
        Product::factory()->create(['name' => 'Munitions', 'category_id' => $restricted->id]);

        $this->user->givePermissionTo('manage products');

        $response = $this->getJson(route('api.register.products.index'), $this->authHeaders());

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_index_excludes_soft_deleted_products(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        Product::factory()->create(['name' => 'Active', 'category_id' => $category->id]);
        Product::factory()->create(['name' => 'Deleted', 'category_id' => $category->id])->delete();

        $response = $this->getJson(route('api.register.products.index'), $this->authHeaders());

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_index_eager_loads_category_and_child_products(): void
    {
        $category = Category::factory()->create(['name' => 'Bar', 'is_restricted' => false]);
        $product = Product::factory()->create(['name' => 'Bière', 'category_id' => $category->id]);
        $child = Product::factory()->create(['name' => 'Schneider', 'category_id' => $category->id]);
        $product->childProducts()->attach($child->id, ['quantity' => 6]);

        $response = $this->getJson(route('api.register.products.index'), $this->authHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.0.category.name', 'Bar');
        $response->assertJsonCount(1, 'data.0.child_products');
    }

    public function test_show_returns_single_product_with_relationships(): void
    {
        $category = Category::factory()->create(['name' => 'Bar', 'is_restricted' => false]);
        $product = Product::factory()->create(['name' => 'Bière', 'category_id' => $category->id]);
        $child = Product::factory()->create(['name' => 'Schneider', 'category_id' => $category->id]);
        $product->childProducts()->attach($child->id, ['quantity' => 6]);

        $response = $this->getJson(route('api.register.products.show', $product), $this->authHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Bière');
        $response->assertJsonPath('data.category.name', 'Bar');
        $response->assertJsonCount(1, 'data.child_products');
    }

    public function test_show_returns_404_for_nonexistent_product(): void
    {
        $response = $this->getJson(route('api.register.products.show', 9999), $this->authHeaders());

        $response->assertNotFound();
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson(route('api.register.products.index'));

        $response->assertUnauthorized();
    }
}
