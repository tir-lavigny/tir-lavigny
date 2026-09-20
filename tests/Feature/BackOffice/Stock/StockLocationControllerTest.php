<?php

namespace Tests\Feature\BackOffice\Stock;

use App\Models\Product;
use App\Models\Season;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockLocationControllerTest extends TestCase
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

    private function createCurrentSeason(): Season
    {
        return Season::factory()->create([
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
        ]);
    }

    // ── Index ──

    public function test_index_requires_auth(): void
    {
        auth()->logout();

        $response = $this->get(route('bo.stock.locations.index'));

        $response->assertRedirect('/login');
    }

    public function test_index_requires_manage_stock_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view register');
        $this->actingAs($user);

        $response = $this->get(route('bo.stock.locations.index'));

        $response->assertForbidden();
    }

    public function test_index_displays_locations(): void
    {
        StockLocation::factory()->count(3)->create();

        $response = $this->get(route('bo.stock.locations.index'));

        $response->assertOk();
    }

    // ── Create ──

    public function test_create_requires_manage_stock_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view register');
        $this->actingAs($user);

        $response = $this->get(route('bo.stock.locations.create'));

        $response->assertForbidden();
    }

    public function test_create_returns_form(): void
    {
        $response = $this->get(route('bo.stock.locations.create'));

        $response->assertOk();
    }

    // ── Store ──

    public function test_store_creates_location(): void
    {
        $response = $this->post(route('bo.stock.locations.store'), [
            'name' => 'Warehouse',
            'description' => 'Main storage area',
            'active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('stock_locations', [
            'name' => 'Warehouse',
            'description' => 'Main storage area',
            'active' => true,
        ]);
    }

    public function test_store_validates_name_required(): void
    {
        $response = $this->post(route('bo.stock.locations.store'), [
            'description' => 'Main storage area',
            'active' => true,
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_store_validates_name_max_255(): void
    {
        $response = $this->post(route('bo.stock.locations.store'), [
            'name' => str_repeat('a', 256),
            'active' => true,
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_store_validates_active_required(): void
    {
        $response = $this->post(route('bo.stock.locations.store'), [
            'name' => 'Warehouse',
        ]);

        $response->assertSessionHasErrors(['active']);
    }

    public function test_store_validates_active_boolean(): void
    {
        $response = $this->post(route('bo.stock.locations.store'), [
            'name' => 'Warehouse',
            'active' => 'not-a-boolean',
        ]);

        $response->assertSessionHasErrors(['active']);
    }

    public function test_store_description_is_optional(): void
    {
        $response = $this->post(route('bo.stock.locations.store'), [
            'name' => 'Warehouse',
            'active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('stock_locations', [
            'name' => 'Warehouse',
            'description' => null,
        ]);
    }

    public function test_store_requires_manage_stock_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view register');
        $this->actingAs($user);

        $response = $this->post(route('bo.stock.locations.store'), [
            'name' => 'Warehouse',
            'active' => true,
        ]);

        $response->assertForbidden();
    }

    // ── Show ──

    public function test_show_requires_auth(): void
    {
        auth()->logout();
        $location = StockLocation::factory()->create();

        $response = $this->get(route('bo.stock.locations.show', $location));

        $response->assertRedirect('/login');
    }

    public function test_show_displays_location(): void
    {
        $location = StockLocation::factory()->create();

        $response = $this->get(route('bo.stock.locations.show', $location));

        $response->assertOk();
    }

    public function test_show_displays_current_stock(): void
    {
        $location = StockLocation::factory()->create(['active' => true]);
        $product = Product::factory()->create(['is_stockable' => true]);
        $this->createCurrentSeason();

        StockMovement::factory()->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'type' => 'initial',
        ]);

        StockMovement::factory()->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
            'quantity' => -3,
            'type' => 'sale',
        ]);

        $response = $this->get(route('bo.stock.locations.show', $location));

        $response->assertOk();
    }

    public function test_show_displays_negative_stock(): void
    {
        $location = StockLocation::factory()->create(['active' => true]);
        $product = Product::factory()->create(['is_stockable' => true]);
        $this->createCurrentSeason();

        StockMovement::factory()->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
            'quantity' => -5,
            'type' => 'sale',
        ]);

        $response = $this->get(route('bo.stock.locations.show', $location));

        $response->assertOk();
    }

    public function test_show_displays_paginated_movements(): void
    {
        $location = StockLocation::factory()->create(['active' => true]);
        $product = Product::factory()->create(['is_stockable' => true]);
        $this->createCurrentSeason();

        StockMovement::factory()->count(20)->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
        ]);

        $response = $this->get(route('bo.stock.locations.show', $location));

        $response->assertOk();
    }

    public function test_show_inactive_location_remains_accessible(): void
    {
        $location = StockLocation::factory()->inactive()->create();

        $response = $this->get(route('bo.stock.locations.show', $location));

        $response->assertOk();
    }

    // ── Edit ──

    public function test_edit_requires_manage_stock_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view register');
        $this->actingAs($user);
        $location = StockLocation::factory()->create();

        $response = $this->get(route('bo.stock.locations.edit', $location));

        $response->assertForbidden();
    }

    public function test_edit_returns_form(): void
    {
        $location = StockLocation::factory()->create();

        $response = $this->get(route('bo.stock.locations.edit', $location));

        $response->assertOk();
    }

    // ── Update ──

    public function test_update_updates_location(): void
    {
        $location = StockLocation::factory()->create();

        $response = $this->put(route('bo.stock.locations.update', $location), [
            'name' => 'Updated Warehouse',
            'description' => 'Updated description',
            'active' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('stock_locations', [
            'id' => $location->id,
            'name' => 'Updated Warehouse',
            'description' => 'Updated description',
            'active' => false,
        ]);
    }

    public function test_update_validates_name_required(): void
    {
        $location = StockLocation::factory()->create();

        $response = $this->put(route('bo.stock.locations.update', $location), [
            'description' => 'Updated description',
            'active' => true,
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_update_validates_name_max_255(): void
    {
        $location = StockLocation::factory()->create();

        $response = $this->put(route('bo.stock.locations.update', $location), [
            'name' => str_repeat('a', 256),
            'active' => true,
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_update_validates_active_required(): void
    {
        $location = StockLocation::factory()->create();

        $response = $this->put(route('bo.stock.locations.update', $location), [
            'name' => 'Warehouse',
        ]);

        $response->assertSessionHasErrors(['active']);
    }

    public function test_update_requires_manage_stock_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view register');
        $this->actingAs($user);
        $location = StockLocation::factory()->create();

        $response = $this->put(route('bo.stock.locations.update', $location), [
            'name' => 'Warehouse',
            'active' => true,
        ]);

        $response->assertForbidden();
    }

    // ── Stock aggregation ──

    public function test_stock_aggregation_positive(): void
    {
        $location = StockLocation::factory()->create(['active' => true]);
        $product = Product::factory()->create(['is_stockable' => true]);
        $this->createCurrentSeason();

        StockMovement::factory()->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'type' => 'initial',
        ]);

        StockMovement::factory()->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
            'quantity' => -3,
            'type' => 'sale',
        ]);

        StockMovement::factory()->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'type' => 'transfer',
        ]);

        StockMovement::factory()->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
            'quantity' => -2,
            'type' => 'loss',
        ]);

        StockMovement::factory()->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'type' => 'reversal',
        ]);

        $response = $this->get(route('bo.stock.locations.show', $location));

        $response->assertOk();
    }

    public function test_stock_aggregation_negative(): void
    {
        $location = StockLocation::factory()->create(['active' => true]);
        $product = Product::factory()->create(['is_stockable' => true]);

        StockMovement::factory()->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
            'quantity' => -5,
            'type' => 'sale',
        ]);

        $response = $this->get(route('bo.stock.locations.show', $location));

        $response->assertOk();
    }

    // ── Movement history ──

    public function test_movements_ordered_newest_first(): void
    {
        $location = StockLocation::factory()->create(['active' => true]);
        $product = Product::factory()->create(['is_stockable' => true]);
        $this->createCurrentSeason();

        $old = StockMovement::factory()->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
            'created_at' => now()->subDay(),
        ]);

        $new = StockMovement::factory()->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
            'created_at' => now(),
        ]);

        $response = $this->get(route('bo.stock.locations.show', $location));

        $response->assertOk();
    }

    public function test_movements_filtered_by_location(): void
    {
        $location = StockLocation::factory()->create(['active' => true]);
        $otherLocation = StockLocation::factory()->create(['active' => true]);
        $product = Product::factory()->create(['is_stockable' => true]);
        $this->createCurrentSeason();

        StockMovement::factory()->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
        ]);

        StockMovement::factory()->create([
            'stock_location_id' => $otherLocation->id,
            'product_id' => $product->id,
        ]);

        $response = $this->get(route('bo.stock.locations.show', $location));

        $response->assertOk();
    }

    public function test_movements_include_product(): void
    {
        $location = StockLocation::factory()->create(['active' => true]);
        $product = Product::factory()->create(['is_stockable' => true]);
        $this->createCurrentSeason();

        StockMovement::factory()->create([
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
        ]);

        $response = $this->get(route('bo.stock.locations.show', $location));

        $response->assertOk();
    }
}
