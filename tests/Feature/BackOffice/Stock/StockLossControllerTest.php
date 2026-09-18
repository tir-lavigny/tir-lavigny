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

class StockLossControllerTest extends TestCase
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

    public function test_index_requires_auth(): void
    {
        auth()->logout();

        $response = $this->get(route('bo.stock.losses.index'));

        $response->assertRedirect('/login');
    }

    public function test_index_returns_stock_losses(): void
    {
        StockMovement::factory()->create(['type' => 'loss']);

        $response = $this->get(route('bo.stock.losses.index'));

        $response->assertOk();
    }

    public function test_create_requires_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view register');
        $this->actingAs($user);

        $response = $this->get(route('bo.stock.losses.create'));

        $response->assertForbidden();
    }

    public function test_create_returns_form(): void
    {
        StockLocation::factory()->create(['active' => true]);

        $response = $this->get(route('bo.stock.losses.create'));

        $response->assertOk();
    }

    public function test_store_records_loss(): void
    {
        $this->createCurrentSeason();
        $location = StockLocation::factory()->create(['active' => true]);
        $product = Product::factory()->create(['is_stockable' => true]);

        $response = $this->post(route('bo.stock.losses.store', $location), [
            'product_id' => $product->id,
            'quantity' => 3,
            'reason' => 'Broken bottles',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'quantity' => -3,
            'type' => 'loss',
            'reason' => 'Broken bottles',
        ]);
    }

    public function test_store_validates_product_id_required(): void
    {
        $location = StockLocation::factory()->create();

        $response = $this->post(route('bo.stock.losses.store', $location), [
            'quantity' => 3,
        ]);

        $response->assertSessionHasErrors(['product_id']);
    }

    public function test_store_validates_quantity_required(): void
    {
        $location = StockLocation::factory()->create();

        $response = $this->post(route('bo.stock.losses.store', $location), [
            'product_id' => 1,
        ]);

        $response->assertSessionHasErrors(['quantity']);
    }

    public function test_store_validates_quantity_min_one(): void
    {
        $location = StockLocation::factory()->create();

        $response = $this->post(route('bo.stock.losses.store', $location), [
            'product_id' => 1,
            'quantity' => 0,
        ]);

        $response->assertSessionHasErrors(['quantity']);
    }

    public function test_store_reason_is_optional(): void
    {
        $this->createCurrentSeason();
        $location = StockLocation::factory()->create(['active' => true]);
        $product = Product::factory()->create(['is_stockable' => true]);

        $response = $this->post(route('bo.stock.losses.store', $location), [
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'quantity' => -5,
            'type' => 'loss',
            'reason' => null,
        ]);
    }

    public function test_store_requires_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view register');
        $this->actingAs($user);

        $location = StockLocation::factory()->create();

        $response = $this->post(route('bo.stock.losses.store', $location), [
            'product_id' => 1,
            'quantity' => 3,
        ]);

        $response->assertForbidden();
    }

    public function test_store_requires_current_season(): void
    {
        $location = StockLocation::factory()->create(['active' => true]);
        $product = Product::factory()->create(['is_stockable' => true]);

        $response = $this->post(route('bo.stock.losses.store', $location), [
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $response->assertNotFound();
    }

    public function test_store_allows_loss_with_insufficient_stock(): void
    {
        $this->createCurrentSeason();
        $location = StockLocation::factory()->create(['active' => true]);
        $product = Product::factory()->create(['is_stockable' => true]);

        $response = $this->post(route('bo.stock.losses.store', $location), [
            'product_id' => $product->id,
            'quantity' => 100,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'quantity' => -100,
            'type' => 'loss',
        ]);
    }

    public function test_show_returns_loss(): void
    {
        $loss = StockMovement::factory()->create(['type' => 'loss']);

        $response = $this->get(route('bo.stock.losses.show', $loss));

        $response->assertOk();
    }

    public function test_route_model_binding_for_location(): void
    {
        $this->createCurrentSeason();
        $location = StockLocation::factory()->create(['active' => true]);
        $product = Product::factory()->create(['is_stockable' => true]);

        $response = $this->post(route('bo.stock.losses.store', $location), [
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('stock_movements', [
            'stock_location_id' => $location->id,
        ]);
    }
}
