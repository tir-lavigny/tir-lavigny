<?php

namespace Tests\Feature\BackOffice\Register;

use App\Models\Category;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\User;
use App\Services\PriceListService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceListControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
        $this->category = Category::factory()->create(['is_restricted' => false]);
    }

    public function test_index_returns_price_lists(): void
    {
        PriceList::factory()->create(['name' => 'Normal']);

        $response = $this->get(route('bo.register.price-lists.index'));

        $response->assertOk();
    }

    public function test_store_creates_price_list_with_items(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id, 'unit_price' => 10.00]);

        $response = $this->post(route('bo.register.price-lists.store'), [
            'name' => 'Normal',
            'notes' => 'Prix de base',
            'items' => [
                ['product_id' => $product->id, 'unit_price' => 12.00],
            ],
        ]);

        $response->assertRedirect(route('bo.register.price-lists.index'));
        $this->assertDatabaseHas('price_lists', ['name' => 'Normal', 'notes' => 'Prix de base']);
        $this->assertDatabaseHas('price_list_items', [
            'product_id' => $product->id,
            'unit_price' => 12.00,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->post(route('bo.register.price-lists.store'), []);

        $response->assertSessionHasErrors(['name', 'items']);
    }

    public function test_create_rolls_back_when_items_fail(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id]);

        try {
            app(PriceListService::class)->create([
                'name' => 'Broken',
                'items' => [
                    ['product_id' => $product->id, 'unit_price' => 10.00],
                    ['product_id' => $product->id, 'unit_price' => 12.00],
                ],
            ]);
            $this->fail('Expected a query exception.');
        } catch (QueryException) {
            $this->assertDatabaseCount('price_lists', 0);
        }
    }

    public function test_update_replaces_items(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id, 'unit_price' => 10.00]);
        $other = Product::factory()->create(['category_id' => $this->category->id, 'unit_price' => 5.00]);
        $priceList = PriceList::factory()->create(['name' => 'Normal']);
        $priceList->items()->create(['product_id' => $product->id, 'unit_price' => 12.00]);

        $response = $this->put(route('bo.register.price-lists.update', $priceList), [
            'name' => 'Normal',
            'items' => [
                ['product_id' => $other->id, 'unit_price' => 6.00],
            ],
        ]);

        $response->assertRedirect(route('bo.register.price-lists.index'));
        $this->assertDatabaseMissing('price_list_items', ['product_id' => $product->id]);
        $this->assertDatabaseHas('price_list_items', [
            'product_id' => $other->id,
            'unit_price' => 6.00,
        ]);
    }

    public function test_preview_reports_statuses(): void
    {
        $changed = Product::factory()->create(['category_id' => $this->category->id, 'unit_price' => 10.00]);
        $unchanged = Product::factory()->create(['category_id' => $this->category->id, 'unit_price' => 3.50]);
        $missing = Product::factory()->create(['category_id' => $this->category->id, 'unit_price' => 4.00]);
        $notInList = Product::factory()->create(['category_id' => $this->category->id, 'unit_price' => 2.00]);

        $priceList = PriceList::factory()->create(['name' => 'Normal']);
        $priceList->items()->createMany([
            ['product_id' => $changed->id, 'unit_price' => 12.00],
            ['product_id' => $unchanged->id, 'unit_price' => 3.50],
            ['product_id' => $missing->id, 'unit_price' => 4.50],
        ]);

        $missing->delete();

        $response = $this->get(route('bo.register.price-lists.preview', $priceList));

        $response->assertOk();
        $response->assertJsonPath('summary.changed', 1);
        $response->assertJsonPath('summary.unchanged', 1);
        $response->assertJsonPath('summary.missing', 1);
        $response->assertJsonPath('summary.not_in_list', 1);
        $response->assertJsonFragment(['product_id' => $notInList->id, 'status' => 'not_in_list']);
    }

    public function test_apply_updates_prices_and_activates_list(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id, 'unit_price' => 10.00]);
        $previousActive = PriceList::factory()->active()->create(['name' => 'Previous']);
        $priceList = PriceList::factory()->create(['name' => 'Normal']);
        $priceList->items()->create(['product_id' => $product->id, 'unit_price' => 12.00]);

        $response = $this->post(route('bo.register.price-lists.apply', $priceList));

        $response->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'unit_price' => 12.00]);
        $this->assertDatabaseHas('price_lists', ['id' => $priceList->id, 'is_active' => true]);
        $this->assertDatabaseHas('price_lists', ['id' => $previousActive->id, 'is_active' => false]);
        $this->assertNotNull($priceList->fresh()->last_applied_at);
    }

    public function test_apply_skips_soft_deleted_products(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id, 'unit_price' => 10.00]);
        $priceList = PriceList::factory()->create(['name' => 'Normal']);
        $priceList->items()->create(['product_id' => $product->id, 'unit_price' => 12.00]);

        $product->delete();

        $response = $this->post(route('bo.register.price-lists.apply', $priceList));

        $response->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'unit_price' => 10.00]);
    }

    public function test_drift_reports_counts(): void
    {
        $changed = Product::factory()->create(['category_id' => $this->category->id, 'unit_price' => 10.00]);
        $missing = Product::factory()->create(['category_id' => $this->category->id, 'unit_price' => 4.00]);
        Product::factory()->create(['category_id' => $this->category->id, 'unit_price' => 2.00]);

        $priceList = PriceList::factory()->create(['name' => 'Normal']);
        $priceList->items()->createMany([
            ['product_id' => $changed->id, 'unit_price' => 12.00],
            ['product_id' => $missing->id, 'unit_price' => 4.50],
        ]);

        $missing->delete();

        $response = $this->get(route('bo.register.price-lists.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('priceLists.0.drift.changed', 1)
            ->where('priceLists.0.drift.missing', 1)
            ->where('priceLists.0.drift.not_in_list', 1));
    }

    public function test_sync_from_catalog_adds_and_removes_products(): void
    {
        $kept = Product::factory()->create(['category_id' => $this->category->id, 'unit_price' => 10.00]);
        $removed = Product::factory()->create(['category_id' => $this->category->id, 'unit_price' => 4.00]);
        $added = Product::factory()->create(['category_id' => $this->category->id, 'unit_price' => 2.00]);

        $priceList = PriceList::factory()->create(['name' => 'Normal']);
        $priceList->items()->createMany([
            ['product_id' => $kept->id, 'unit_price' => 12.00],
            ['product_id' => $removed->id, 'unit_price' => 4.50],
        ]);

        $removed->delete();

        $response = $this->post(route('bo.register.price-lists.sync-from-catalog', $priceList));

        $response->assertRedirect();
        $this->assertDatabaseHas('price_list_items', ['product_id' => $kept->id, 'unit_price' => 12.00]);
        $this->assertDatabaseMissing('price_list_items', ['product_id' => $removed->id]);
        $this->assertDatabaseHas('price_list_items', ['product_id' => $added->id, 'unit_price' => 2.00]);
    }

    public function test_duplicate_copies_list(): void
    {
        $product = Product::factory()->create(['category_id' => $this->category->id, 'unit_price' => 10.00]);
        $priceList = PriceList::factory()->active()->create(['name' => 'Normal']);
        $priceList->items()->create(['product_id' => $product->id, 'unit_price' => 12.00]);

        $response = $this->post(route('bo.register.price-lists.duplicate', $priceList));

        $response->assertRedirect();
        $copy = PriceList::where('name', 'Normal (copie)')->first();
        $this->assertNotNull($copy);
        $this->assertFalse($copy->is_active);
        $this->assertDatabaseHas('price_list_items', ['price_list_id' => $copy->id, 'unit_price' => 12.00]);
    }

    public function test_destroy_deletes_active_list(): void
    {
        $priceList = PriceList::factory()->active()->create(['name' => 'Normal']);

        $response = $this->delete(route('bo.register.price-lists.destroy', $priceList));

        $response->assertRedirect(route('bo.register.price-lists.index'));
        $this->assertDatabaseMissing('price_lists', ['id' => $priceList->id]);
    }

    public function test_user_without_permission_cannot_access(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('bo.register.price-lists.index'));

        $response->assertForbidden();
    }
}
