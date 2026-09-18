<?php

namespace Tests\Feature\BackOffice\Stock;

use App\Enums\StockCountType;
use App\Models\Product;
use App\Models\Season;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockLocation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockCountControllerTest extends TestCase
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

        $response = $this->get(route('bo.stock.counts.index'));

        $response->assertRedirect('/login');
    }

    public function test_index_returns_stock_counts(): void
    {
        StockCount::factory()->create();

        $response = $this->get(route('bo.stock.counts.index'));

        $response->assertOk();
    }

    public function test_create_requires_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view register');
        $this->actingAs($user);

        $response = $this->get(route('bo.stock.counts.create'));

        $response->assertForbidden();
    }

    public function test_create_returns_form(): void
    {
        StockLocation::factory()->create(['active' => true]);

        $response = $this->get(route('bo.stock.counts.create'));

        $response->assertOk();
    }

    public function test_store_creates_stock_count(): void
    {
        $this->createCurrentSeason();
        $location = StockLocation::factory()->create(['active' => true]);
        Product::factory()->create(['is_stockable' => true]);

        $response = $this->post(route('bo.stock.counts.store'), [
            'stock_location_id' => $location->id,
            'type' => StockCountType::Initial->value,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('stock_counts', [
            'stock_location_id' => $location->id,
            'type' => StockCountType::Initial,
            'status' => 'pending',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->post(route('bo.stock.counts.store'), []);

        $response->assertSessionHasErrors(['stock_location_id', 'type']);
    }

    public function test_store_validates_location_exists(): void
    {
        $response = $this->post(route('bo.stock.counts.store'), [
            'stock_location_id' => 9999,
            'type' => StockCountType::Initial->value,
        ]);

        $response->assertSessionHasErrors(['stock_location_id']);
    }

    public function test_store_validates_type_enum(): void
    {
        $location = StockLocation::factory()->create();

        $response = $this->post(route('bo.stock.counts.store'), [
            'stock_location_id' => $location->id,
            'type' => 'invalid',
        ]);

        $response->assertSessionHasErrors(['type']);
    }

    public function test_store_requires_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view register');
        $this->actingAs($user);

        $response = $this->post(route('bo.stock.counts.store'), [
            'stock_location_id' => 1,
            'type' => StockCountType::Initial->value,
        ]);

        $response->assertForbidden();
    }

    public function test_store_requires_current_season(): void
    {
        $location = StockLocation::factory()->create(['active' => true]);

        $response = $this->post(route('bo.stock.counts.store'), [
            'stock_location_id' => $location->id,
            'type' => StockCountType::Initial->value,
        ]);

        $response->assertNotFound();
    }

    public function test_show_returns_stock_count(): void
    {
        $count = StockCount::factory()->create();
        StockCountLine::factory()->create(['stock_count_id' => $count->id]);

        $response = $this->get(route('bo.stock.counts.show', $count));

        $response->assertOk();
    }

    public function test_complete_updates_count_lines_and_completes(): void
    {
        $season = $this->createCurrentSeason();
        $location = StockLocation::factory()->create(['active' => true]);
        $product = Product::factory()->create(['is_stockable' => true]);

        $count = StockCount::factory()->create([
            'season_id' => $season->id,
            'stock_location_id' => $location->id,
            'type' => StockCountType::Initial,
            'status' => 'pending',
        ]);

        StockCountLine::create([
            'stock_count_id' => $count->id,
            'product_id' => $product->id,
            'expected_quantity' => 10,
            'counted_quantity' => null,
        ]);

        $response = $this->post(route('bo.stock.counts.complete', $count), [
            'lines' => [
                [
                    'product_id' => $product->id,
                    'counted_quantity' => 15,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('stock_counts', [
            'id' => $count->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('stock_count_lines', [
            'stock_count_id' => $count->id,
            'product_id' => $product->id,
            'counted_quantity' => 15,
        ]);
    }

    public function test_complete_validates_lines_required(): void
    {
        $count = StockCount::factory()->create();

        $response = $this->post(route('bo.stock.counts.complete', $count), []);

        $response->assertSessionHasErrors(['lines']);
    }

    public function test_complete_validates_lines_min_one(): void
    {
        $count = StockCount::factory()->create();

        $response = $this->post(route('bo.stock.counts.complete', $count), [
            'lines' => [],
        ]);

        $response->assertSessionHasErrors(['lines']);
    }

    public function test_complete_validates_product_id_required(): void
    {
        $count = StockCount::factory()->create();

        $response = $this->post(route('bo.stock.counts.complete', $count), [
            'lines' => [
                ['counted_quantity' => 5],
            ],
        ]);

        $response->assertSessionHasErrors(['lines.0.product_id']);
    }

    public function test_complete_validates_counted_quantity_required(): void
    {
        $count = StockCount::factory()->create();

        $response = $this->post(route('bo.stock.counts.complete', $count), [
            'lines' => [
                ['product_id' => 1],
            ],
        ]);

        $response->assertSessionHasErrors(['lines.0.counted_quantity']);
    }

    public function test_complete_allows_negative_counted_quantity(): void
    {
        $season = $this->createCurrentSeason();
        $location = StockLocation::factory()->create(['active' => true]);
        $product = Product::factory()->create(['is_stockable' => true]);

        $count = StockCount::factory()->create([
            'season_id' => $season->id,
            'stock_location_id' => $location->id,
            'type' => StockCountType::Adjustment,
            'status' => 'pending',
        ]);

        StockCountLine::create([
            'stock_count_id' => $count->id,
            'product_id' => $product->id,
            'expected_quantity' => 10,
            'counted_quantity' => null,
        ]);

        $response = $this->post(route('bo.stock.counts.complete', $count), [
            'lines' => [
                [
                    'product_id' => $product->id,
                    'counted_quantity' => -3,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('stock_counts', [
            'id' => $count->id,
            'status' => 'completed',
        ]);
    }

    public function test_complete_allows_zero_counted_quantity(): void
    {
        $season = $this->createCurrentSeason();
        $location = StockLocation::factory()->create(['active' => true]);
        $product = Product::factory()->create(['is_stockable' => true]);

        $count = StockCount::factory()->create([
            'season_id' => $season->id,
            'stock_location_id' => $location->id,
            'type' => StockCountType::Adjustment,
            'status' => 'pending',
        ]);

        StockCountLine::create([
            'stock_count_id' => $count->id,
            'product_id' => $product->id,
            'expected_quantity' => 10,
            'counted_quantity' => null,
        ]);

        $response = $this->post(route('bo.stock.counts.complete', $count), [
            'lines' => [
                [
                    'product_id' => $product->id,
                    'counted_quantity' => 0,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('stock_counts', [
            'id' => $count->id,
            'status' => 'completed',
        ]);
    }

    public function test_complete_requires_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view register');
        $this->actingAs($user);

        $count = StockCount::factory()->create();

        $response = $this->post(route('bo.stock.counts.complete', $count), [
            'lines' => [
                ['product_id' => 1, 'counted_quantity' => 5],
            ],
        ]);

        $response->assertForbidden();
    }

    public function test_route_model_binding_works(): void
    {
        $count = StockCount::factory()->create();

        $response = $this->get(route('bo.stock.counts.show', $count));

        $response->assertOk();
    }
}
