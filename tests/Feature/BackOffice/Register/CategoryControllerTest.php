<?php

namespace Tests\Feature\BackOffice\Register;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
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

    public function test_index_returns_categories(): void
    {
        Category::factory()->create(['name' => 'Tir']);
        Category::factory()->create(['name' => 'Bar']);

        $response = $this->get(route('bo.register.categories.index'));

        $response->assertOk();
    }

    public function test_store_creates_category(): void
    {
        $response = $this->post(route('bo.register.categories.store'), [
            'name' => 'Nourriture',
            'is_restricted' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('categories', ['name' => 'Nourriture']);
    }

    public function test_store_validates_name_required(): void
    {
        $response = $this->post(route('bo.register.categories.store'), [
            'name' => '',
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_store_validates_name_unique(): void
    {
        Category::factory()->create(['name' => 'Tir']);

        $response = $this->post(route('bo.register.categories.store'), [
            'name' => 'Tir',
            'is_restricted' => false,
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_edit_returns_form(): void
    {
        $category = Category::factory()->create();

        $response = $this->get(route('bo.register.categories.edit', $category));

        $response->assertOk();
    }

    public function test_update_modifies_category(): void
    {
        $category = Category::factory()->create(['name' => 'Old Name']);

        $response = $this->put(route('bo.register.categories.update', $category), [
            'name' => 'New Name',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'New Name']);
    }

    public function test_destroy_soft_deletes_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->delete(route('bo.register.categories.destroy', $category));

        $response->assertRedirect();
        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }

    public function test_destroy_rejects_category_containing_correction_product(): void
    {
        $category = Category::factory()->create(['name' => 'Correction', 'is_restricted' => true]);
        Product::factory()->create([
            'name' => 'Correction',
            'unit_price' => 0,
            'allows_custom_price' => true,
            'is_stockable' => false,
            'category_id' => $category->id,
        ]);

        $response = $this->delete(route('bo.register.categories.destroy', $category));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'deleted_at' => null]);
    }

    public function test_store_requires_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view register');
        $this->actingAs($user);

        $response = $this->post(route('bo.register.categories.store'), [
            'name' => 'Test',
        ]);

        $response->assertForbidden();
    }
}
