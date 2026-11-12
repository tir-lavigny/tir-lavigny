<?php

namespace Tests\Feature\BackOffice\Stock;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Season;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferControllerTest extends TestCase
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

        $response = $this->get(route('bo.stock.transfers.index'));

        $response->assertRedirect('/login');
    }

    public function test_index_returns_stock_transfers(): void
    {
        StockTransfer::factory()->create();

        $response = $this->get(route('bo.stock.transfers.index'));

        $response->assertOk();
    }

    public function test_create_requires_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['access backoffice', 'view register']);
        $this->actingAs($user);

        $response = $this->get(route('bo.stock.transfers.create'));

        $response->assertForbidden();
    }

    public function test_create_returns_form(): void
    {
        StockLocation::factory()->create(['active' => true]);

        $response = $this->get(route('bo.stock.transfers.create'));

        $response->assertOk();
    }

    public function test_store_creates_transfer(): void
    {
        $season = $this->createCurrentSeason();
        $fromLocation = StockLocation::factory()->create(['active' => true]);
        $toLocation = StockLocation::factory()->create(['active' => true]);
        $product = Product::factory()->create(['is_stockable' => true]);

        StockMovement::factory()->create([
            'product_id' => $product->id,
            'stock_location_id' => $fromLocation->id,
            'quantity' => 20,
            'type' => StockMovementType::Purchase,
            'season_id' => $season->id,
        ]);

        $response = $this->post(route('bo.stock.transfers.store', $fromLocation), [
            'to_location_id' => $toLocation->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                ],
            ],
            'reason' => 'Replenishment',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('stock_transfers', [
            'from_location_id' => $fromLocation->id,
            'to_location_id' => $toLocation->id,
            'reason' => 'Replenishment',
        ]);
    }

    public function test_store_validates_to_location_required(): void
    {
        $fromLocation = StockLocation::factory()->create();

        $response = $this->post(route('bo.stock.transfers.store', $fromLocation), [
            'items' => [['product_id' => 1, 'quantity' => 10]],
        ]);

        $response->assertSessionHasErrors(['to_location_id']);
    }

    public function test_store_validates_items_required(): void
    {
        $fromLocation = StockLocation::factory()->create();
        $toLocation = StockLocation::factory()->create();

        $response = $this->post(route('bo.stock.transfers.store', $fromLocation), [
            'to_location_id' => $toLocation->id,
        ]);

        $response->assertSessionHasErrors(['items']);
    }

    public function test_store_validates_items_min_one(): void
    {
        $fromLocation = StockLocation::factory()->create();
        $toLocation = StockLocation::factory()->create();

        $response = $this->post(route('bo.stock.transfers.store', $fromLocation), [
            'to_location_id' => $toLocation->id,
            'items' => [],
        ]);

        $response->assertSessionHasErrors(['items']);
    }

    public function test_store_validates_product_id_required(): void
    {
        $fromLocation = StockLocation::factory()->create();
        $toLocation = StockLocation::factory()->create();

        $response = $this->post(route('bo.stock.transfers.store', $fromLocation), [
            'to_location_id' => $toLocation->id,
            'items' => [
                ['quantity' => 10],
            ],
        ]);

        $response->assertSessionHasErrors(['items.0.product_id']);
    }

    public function test_store_validates_quantity_min_one(): void
    {
        $fromLocation = StockLocation::factory()->create();
        $toLocation = StockLocation::factory()->create();

        $response = $this->post(route('bo.stock.transfers.store', $fromLocation), [
            'to_location_id' => $toLocation->id,
            'items' => [
                ['product_id' => 1, 'quantity' => 0],
            ],
        ]);

        $response->assertSessionHasErrors(['items.0.quantity']);
    }

    public function test_store_validates_duplicate_products_rejected(): void
    {
        $fromLocation = StockLocation::factory()->create();
        $toLocation = StockLocation::factory()->create();
        $product = Product::factory()->create();

        $response = $this->post(route('bo.stock.transfers.store', $fromLocation), [
            'to_location_id' => $toLocation->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5],
                ['product_id' => $product->id, 'quantity' => 10],
            ],
        ]);

        $response->assertSessionHasErrors(['items.1.product_id']);
    }

    public function test_store_requires_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['access backoffice', 'view register']);
        $this->actingAs($user);

        $fromLocation = StockLocation::factory()->create();

        $response = $this->post(route('bo.stock.transfers.store', $fromLocation), [
            'to_location_id' => 1,
            'items' => [['product_id' => 1, 'quantity' => 10]],
        ]);

        $response->assertForbidden();
    }

    public function test_store_does_not_requires_current_season(): void
    {
        $fromLocation = StockLocation::factory()->create(['active' => true]);
        $toLocation = StockLocation::factory()->create(['active' => true]);
        $product = Product::factory()->create(['is_stockable' => true]);

        $response = $this->post(route('bo.stock.transfers.store', $fromLocation), [
            'to_location_id' => $toLocation->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10],
            ],
        ]);

        $response->assertRedirect();
    }

    public function test_store_rejects_transfer_with_insufficient_stock(): void
    {
        $this->createCurrentSeason();
        $fromLocation = StockLocation::factory()->create(['active' => true]);
        $toLocation = StockLocation::factory()->create(['active' => true]);
        $product = Product::factory()->create(['is_stockable' => true]);

        $response = $this->post(route('bo.stock.transfers.store', $fromLocation), [
            'to_location_id' => $toLocation->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 100],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['items']);
        $this->assertDatabaseCount('stock_transfers', 0);
    }

    public function test_store_reason_is_optional(): void
    {
        $season = $this->createCurrentSeason();
        $fromLocation = StockLocation::factory()->create(['active' => true]);
        $toLocation = StockLocation::factory()->create(['active' => true]);
        $product = Product::factory()->create(['is_stockable' => true]);

        StockMovement::factory()->create([
            'product_id' => $product->id,
            'stock_location_id' => $fromLocation->id,
            'quantity' => 10,
            'type' => StockMovementType::Purchase,
            'season_id' => $season->id,
        ]);

        $response = $this->post(route('bo.stock.transfers.store', $fromLocation), [
            'to_location_id' => $toLocation->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('stock_transfers', [
            'from_location_id' => $fromLocation->id,
            'to_location_id' => $toLocation->id,
            'reason' => null,
        ]);
    }

    public function test_store_exact_available_stock_succeeds(): void
    {
        $season = $this->createCurrentSeason();
        $fromLocation = StockLocation::factory()->create(['active' => true]);
        $toLocation = StockLocation::factory()->create(['active' => true]);
        $product = Product::factory()->create(['is_stockable' => true]);

        StockMovement::factory()->create([
            'product_id' => $product->id,
            'stock_location_id' => $fromLocation->id,
            'quantity' => 10,
            'type' => StockMovementType::Purchase,
            'season_id' => $season->id,
        ]);

        $response = $this->post(route('bo.stock.transfers.store', $fromLocation), [
            'to_location_id' => $toLocation->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('stock_transfers', [
            'from_location_id' => $fromLocation->id,
        ]);
    }

    public function test_store_zero_stock_rejects_transfer(): void
    {
        $this->createCurrentSeason();
        $fromLocation = StockLocation::factory()->create(['active' => true]);
        $toLocation = StockLocation::factory()->create(['active' => true]);
        $product = Product::factory()->create(['is_stockable' => true]);

        $response = $this->post(route('bo.stock.transfers.store', $fromLocation), [
            'to_location_id' => $toLocation->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['items']);
        $this->assertDatabaseCount('stock_transfers', 0);
    }

    public function test_store_negative_stock_rejects_transfer(): void
    {
        $season = $this->createCurrentSeason();
        $fromLocation = StockLocation::factory()->create(['active' => true]);
        $toLocation = StockLocation::factory()->create(['active' => true]);
        $product = Product::factory()->create(['is_stockable' => true]);

        StockMovement::factory()->create([
            'product_id' => $product->id,
            'stock_location_id' => $fromLocation->id,
            'quantity' => -3,
            'type' => StockMovementType::Sale,
            'season_id' => $season->id,
        ]);

        $response = $this->post(route('bo.stock.transfers.store', $fromLocation), [
            'to_location_id' => $toLocation->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['items']);
        $this->assertDatabaseCount('stock_transfers', 0);
    }

    public function test_show_returns_transfer(): void
    {
        $transfer = StockTransfer::factory()->create();

        $response = $this->get(route('bo.stock.transfers.show', $transfer));

        $response->assertOk();
    }

    public function test_route_model_binding_for_source_location(): void
    {
        $season = $this->createCurrentSeason();
        $fromLocation = StockLocation::factory()->create(['active' => true]);
        $toLocation = StockLocation::factory()->create(['active' => true]);
        $product = Product::factory()->create(['is_stockable' => true]);

        StockMovement::factory()->create([
            'product_id' => $product->id,
            'stock_location_id' => $fromLocation->id,
            'quantity' => 20,
            'type' => StockMovementType::Purchase,
            'season_id' => $season->id,
        ]);

        $response = $this->post(route('bo.stock.transfers.store', $fromLocation), [
            'to_location_id' => $toLocation->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('stock_transfers', [
            'from_location_id' => $fromLocation->id,
        ]);
    }
}
