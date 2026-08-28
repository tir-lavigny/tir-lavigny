<?php

namespace Tests\Feature\API\Register;

use App\Models\Category;
use App\Models\OperatingDay;
use App\Models\Product;
use App\Models\Season;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\User;
use App\Models\Voucher;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\API\AuthTestHelper;
use Tests\TestCase;

class VoucherValidationControllerTest extends TestCase
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

    public function test_validate_returns_valid_for_percent_voucher(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->subMonth(),
            'end_date' => Carbon::now()->addMonth(),
        ]);
        $voucher = Voucher::factory()->percent()->create([
            'code' => 'TEST10',
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $response = $this->postJson(route('api.register.vouchers.validate'), [
            'code' => 'TEST10',
            'product_id' => $product->id,
            'season_id' => $season->id,
        ], $this->authHeaders());

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'valid' => true,
                'type' => 'percent',
                'value' => (float) $voucher->discount_percent,
            ],
        ]);
    }

    public function test_validate_returns_valid_for_fixed_voucher(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->subMonth(),
            'end_date' => Carbon::now()->addMonth(),
        ]);
        $voucher = Voucher::factory()->fixed()->create([
            'code' => 'FIX5',
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $response = $this->postJson(route('api.register.vouchers.validate'), [
            'code' => 'FIX5',
            'product_id' => $product->id,
            'season_id' => $season->id,
        ], $this->authHeaders());

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'valid' => true,
                'type' => 'fixed',
                'value' => (float) $voucher->discount_fixed,
            ],
        ]);
    }

    public function test_validate_returns_invalid_for_used_voucher(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->subMonth(),
            'end_date' => Carbon::now()->addMonth(),
        ]);
        $voucher = Voucher::factory()->percent()->create([
            'code' => 'USED',
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        $ticket = Ticket::factory()->cash()->create(['operating_day_id' => $day->id]);
        TicketItem::factory()->create([
            'ticket_id' => $ticket->id,
            'product_id' => $product->id,
            'voucher_id' => $voucher->id,
        ]);

        $response = $this->postJson(route('api.register.vouchers.validate'), [
            'code' => 'USED',
            'product_id' => $product->id,
            'season_id' => $season->id,
        ], $this->authHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.valid', false);
        $response->assertJsonStructure(['data' => ['valid', 'error']]);
    }

    public function test_validate_returns_invalid_for_wrong_product(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $otherProduct = Product::factory()->create(['category_id' => $category->id]);
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->subMonth(),
            'end_date' => Carbon::now()->addMonth(),
        ]);
        Voucher::factory()->percent()->create([
            'code' => 'WRONG',
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $response = $this->postJson(route('api.register.vouchers.validate'), [
            'code' => 'WRONG',
            'product_id' => $otherProduct->id,
            'season_id' => $season->id,
        ], $this->authHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.valid', false);
    }

    public function test_validate_returns_invalid_for_wrong_season(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->subMonth(),
            'end_date' => Carbon::now()->addMonth(),
        ]);
        $otherSeason = Season::factory()->create([
            'start_date' => Carbon::now()->subMonth(),
            'end_date' => Carbon::now()->addMonth(),
        ]);
        Voucher::factory()->percent()->create([
            'code' => 'WRONGSEASON',
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $response = $this->postJson(route('api.register.vouchers.validate'), [
            'code' => 'WRONGSEASON',
            'product_id' => $product->id,
            'season_id' => $otherSeason->id,
        ], $this->authHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.valid', false);
    }

    public function test_validate_returns_invalid_for_inactive_season(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->subYear(),
            'end_date' => Carbon::now()->subMonth(),
        ]);
        Voucher::factory()->percent()->create([
            'code' => 'EXPIRED',
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $response = $this->postJson(route('api.register.vouchers.validate'), [
            'code' => 'EXPIRED',
            'product_id' => $product->id,
            'season_id' => $season->id,
        ], $this->authHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.valid', false);
    }

    public function test_validate_fails_with_missing_required_fields(): void
    {
        $response = $this->postJson(route('api.register.vouchers.validate'), [], $this->authHeaders());

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['code', 'product_id', 'season_id']);
    }

    public function test_validate_fails_with_nonexistent_voucher_code(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->subMonth(),
            'end_date' => Carbon::now()->addMonth(),
        ]);

        $response = $this->postJson(route('api.register.vouchers.validate'), [
            'code' => 'NONEXISTENT',
            'product_id' => $product->id,
            'season_id' => $season->id,
        ], $this->authHeaders());

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['code']);
    }

    public function test_validate_requires_authentication(): void
    {
        $response = $this->postJson(route('api.register.vouchers.validate'), [
            'code' => 'TEST',
            'product_id' => 1,
            'season_id' => 1,
        ]);

        $response->assertUnauthorized();
    }
}
