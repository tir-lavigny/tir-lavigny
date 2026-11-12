<?php

namespace Tests\Feature\BackOffice\Register;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    }

    public function test_index_returns_products(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        Product::factory()->create(['name' => 'Bière', 'category_id' => $category->id]);

        $response = $this->get(route('bo.register.products.index'));

        $response->assertOk();
    }

    public function test_index_searches_products_without_accents(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        Product::factory()->create(['name' => 'Bière', 'category_id' => $category->id]);
        Product::factory()->create(['name' => 'Café', 'category_id' => $category->id]);

        $response = $this->get(route('bo.register.products.index', ['search' => 'biere']));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Bière')
            ->where('filters.search', 'biere')
        );
    }

    public function test_store_creates_product(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);

        $response = $this->post(route('bo.register.products.store'), [
            'name' => 'Jus de pomme',
            'unit_price' => 4.50,
            'allows_custom_price' => false,
            'category_id' => $category->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('products', [
            'name' => 'Jus de pomme',
            'unit_price' => 4.50,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->post(route('bo.register.products.store'), []);

        $response->assertSessionHasErrors(['name', 'unit_price', 'category_id']);
    }

    public function test_show_returns_product(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $response = $this->get(route('bo.register.products.show', $product));

        $response->assertOk();
    }

    public function test_edit_returns_form(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $response = $this->get(route('bo.register.products.edit', $product));

        $response->assertOk();
    }

    public function test_update_modifies_product(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create([
            'name' => 'Old',
            'category_id' => $category->id,
        ]);

        $response = $this->put(route('bo.register.products.update', $product), [
            'name' => 'New',
            'unit_price' => $product->unit_price,
            'category_id' => $category->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'New']);
    }

    public function test_destroy_soft_deletes_product(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $response = $this->delete(route('bo.register.products.destroy', $product));

        $response->assertRedirect();
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_update_rejects_stockable_flag_on_correction_product(): void
    {
        $product = $this->createCorrectionProduct();

        $response = $this->put(route('bo.register.products.update', $product), [
            'name' => 'Correction',
            'unit_price' => 0,
            'category_id' => $product->category_id,
            'allows_custom_price' => true,
            'is_stockable' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertFalse($product->fresh()->is_stockable);
    }

    public function test_update_rejects_price_change_on_correction_product(): void
    {
        $product = $this->createCorrectionProduct();

        $response = $this->put(route('bo.register.products.update', $product), [
            'name' => 'Correction',
            'unit_price' => 10,
            'category_id' => $product->category_id,
            'allows_custom_price' => true,
            'is_stockable' => false,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'unit_price' => 0]);
    }

    public function test_update_rejects_renaming_correction_product(): void
    {
        $product = $this->createCorrectionProduct();

        $response = $this->put(route('bo.register.products.update', $product), [
            'name' => 'Correction libre',
            'unit_price' => 0,
            'category_id' => $product->category_id,
            'allows_custom_price' => true,
            'is_stockable' => false,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Correction']);
    }

    public function test_update_allows_saving_correction_product_unchanged(): void
    {
        $product = $this->createCorrectionProduct();

        $response = $this->put(route('bo.register.products.update', $product), [
            'name' => 'Correction',
            'unit_price' => 0,
            'category_id' => $product->category_id,
            'allows_custom_price' => true,
            'is_stockable' => false,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertFalse($product->fresh()->is_stockable);
    }

    public function test_destroy_rejects_deleting_correction_product(): void
    {
        $product = $this->createCorrectionProduct();

        $response = $this->delete(route('bo.register.products.destroy', $product));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'deleted_at' => null]);
    }

    private function createCorrectionProduct(): Product
    {
        $category = Category::factory()->create(['name' => 'Correction', 'is_restricted' => true]);

        return Product::factory()->create([
            'name' => 'Correction',
            'unit_price' => 0,
            'allows_custom_price' => true,
            'is_stockable' => false,
            'category_id' => $category->id,
        ]);
    }

    public function test_attach_child_creates_pivot(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $parent = Product::factory()->create(['category_id' => $category->id]);
        $child = Product::factory()->create(['category_id' => $category->id]);

        $response = $this->post(route('bo.register.products.attach-child', $parent), [
            'child_product_id' => $child->id,
            'quantity' => 6,
        ]);

        $response->assertRedirect();
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

        $response = $this->delete(route('bo.register.products.detach-child', [$parent, $child]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('product_product', [
            'product_id' => $parent->id,
            'child_product_id' => $child->id,
        ]);
    }

    public function test_store_requires_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view register');
        $this->actingAs($user);

        $category = Category::factory()->create(['is_restricted' => false]);

        $response = $this->post(route('bo.register.products.store'), [
            'name' => 'Test',
            'unit_price' => 1.00,
            'category_id' => $category->id,
        ]);

        $response->assertForbidden();
    }
}
