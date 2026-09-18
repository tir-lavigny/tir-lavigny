<?php

namespace Tests\Unit\Http\Requests\API\Register;

use App\Http\Requests\API\Register\StoreTicketRequest;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreTicketRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('admin');
    }

    private function makeRequest(array $data): StoreTicketRequest
    {
        $request = new StoreTicketRequest;
        $request->setUserResolver(fn () => $this->user);
        $request->merge($data);

        return $request;
    }

    private function passes(array $data): bool
    {
        request()->merge($data);
        $request = $this->makeRequest($data);
        $validator = Validator::make($request->all(), $request->rules());

        return $validator->passes();
    }

    private function fails(array $data): bool
    {
        request()->merge($data);
        $request = $this->makeRequest($data);
        $validator = Validator::make($request->all(), $request->rules());

        return $validator->fails();
    }

    private int $categoryCounter = 0;

    private function createCategory(): int
    {
        $this->categoryCounter++;

        return DB::table('categories')->insertGetId([
            'name' => "Test Category {$this->categoryCounter}",
            'is_restricted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOperatingDay(): int
    {
        $seasonId = DB::table('seasons')->insertGetId([
            'name' => 'Test Season',
            'start_date' => Carbon::now()->startOfYear()->toDateString(),
            'end_date' => Carbon::now()->endOfYear()->toDateString(),
            'initial_balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('operating_days')->insertGetId([
            'date' => Carbon::now()->toDateString(),
            'started_at' => Carbon::now(),
            'season_id' => $seasonId,
            'total_cash' => 0,
            'total_twint' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createStockLocation(): int
    {
        return DB::table('stock_locations')->insertGetId([
            'name' => 'Test Location',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createProduct(bool $allowsCustom = false): int
    {
        return DB::table('products')->insertGetId([
            'name' => 'Test Product',
            'unit_price' => 10.00,
            'allows_custom_price' => $allowsCustom,
            'category_id' => $this->createCategory(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_payment_method_is_required(): void
    {
        $this->assertTrue($this->fails([]));
    }

    public function test_payment_method_must_be_valid(): void
    {
        $this->assertTrue($this->fails(['payment_method' => 'credit']));
    }

    public function test_payment_method_accepts_cash(): void
    {
        $operatingDayId = $this->createOperatingDay();
        $productId = $this->createProduct();
        $locationId = $this->createStockLocation();

        $this->assertTrue($this->passes([
            'payment_method' => 'cash',
            'operating_day_id' => $operatingDayId,
            'sold_at' => now()->toDateTimeString(),
            'stock_location_id' => $locationId,
            'items' => [['product_id' => $productId, 'quantity' => 1]],
        ]));
    }

    public function test_payment_method_accepts_twint(): void
    {
        $operatingDayId = $this->createOperatingDay();
        $productId = $this->createProduct();
        $locationId = $this->createStockLocation();

        $this->assertTrue($this->passes([
            'payment_method' => 'twint',
            'operating_day_id' => $operatingDayId,
            'sold_at' => now()->toDateTimeString(),
            'stock_location_id' => $locationId,
            'items' => [['product_id' => $productId, 'quantity' => 1]],
        ]));
    }

    public function test_operating_day_id_is_required(): void
    {
        $this->assertTrue($this->fails([
            'payment_method' => 'cash',
            'items' => [['product_id' => 1, 'quantity' => 1]],
        ]));
    }

    public function test_operating_day_id_must_exist(): void
    {
        $this->assertTrue($this->fails([
            'payment_method' => 'cash',
            'operating_day_id' => 9999,
            'items' => [['product_id' => 1, 'quantity' => 1]],
        ]));
    }

    public function test_items_is_required(): void
    {
        $operatingDayId = $this->createOperatingDay();

        $this->assertTrue($this->fails([
            'payment_method' => 'cash',
            'operating_day_id' => $operatingDayId,
        ]));
    }

    public function test_items_must_have_at_least_one(): void
    {
        $operatingDayId = $this->createOperatingDay();

        $this->assertTrue($this->fails([
            'payment_method' => 'cash',
            'operating_day_id' => $operatingDayId,
            'items' => [],
        ]));
    }

    public function test_item_product_id_is_required(): void
    {
        $operatingDayId = $this->createOperatingDay();

        $this->assertTrue($this->fails([
            'payment_method' => 'cash',
            'operating_day_id' => $operatingDayId,
            'items' => [['quantity' => 1]],
        ]));
    }

    public function test_item_quantity_must_be_at_least_one(): void
    {
        $operatingDayId = $this->createOperatingDay();
        $productId = $this->createProduct();

        $this->assertTrue($this->fails([
            'payment_method' => 'cash',
            'operating_day_id' => $operatingDayId,
            'items' => [['product_id' => $productId, 'quantity' => 0]],
        ]));
    }

    public function test_custom_unit_price_required_when_product_allows_custom(): void
    {
        $operatingDayId = $this->createOperatingDay();
        $productId = $this->createProduct(allowsCustom: true);
        $locationId = $this->createStockLocation();

        $data = [
            'payment_method' => 'cash',
            'operating_day_id' => $operatingDayId,
            'stock_location_id' => $locationId,
            'items' => [['product_id' => $productId, 'quantity' => 1]],
        ];

        $request = new StoreTicketRequest;
        $request->setUserResolver(fn () => $this->user);
        $request->merge($data);

        $validator = Validator::make($data, $request->rules());
        $request->withValidator($validator);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('items.0.custom_unit_price'));
    }

    public function test_custom_unit_price_accepted_when_product_allows_custom(): void
    {
        $operatingDayId = $this->createOperatingDay();
        $productId = $this->createProduct(allowsCustom: true);
        $locationId = $this->createStockLocation();

        $data = [
            'payment_method' => 'cash',
            'operating_day_id' => $operatingDayId,
            'sold_at' => now()->toDateTimeString(),
            'stock_location_id' => $locationId,
            'items' => [['product_id' => $productId, 'quantity' => 1, 'custom_unit_price' => 15.00]],
        ];

        $request = new StoreTicketRequest;
        $request->setUserResolver(fn () => $this->user);
        $request->merge($data);

        $validator = Validator::make($data, $request->rules());
        $request->withValidator($validator);

        $this->assertTrue($validator->passes());
    }

    public function test_custom_unit_price_not_required_when_product_does_not_allow_custom(): void
    {
        $operatingDayId = $this->createOperatingDay();
        $productId = $this->createProduct(allowsCustom: false);
        $locationId = $this->createStockLocation();

        $data = [
            'payment_method' => 'cash',
            'operating_day_id' => $operatingDayId,
            'sold_at' => now()->toDateTimeString(),
            'stock_location_id' => $locationId,
            'items' => [['product_id' => $productId, 'quantity' => 1]],
        ];

        $request = new StoreTicketRequest;
        $request->setUserResolver(fn () => $this->user);
        $request->merge($data);

        $validator = Validator::make($data, $request->rules());
        $request->withValidator($validator);

        $this->assertTrue($validator->passes());
    }

    public function test_voucher_id_must_exist(): void
    {
        $operatingDayId = $this->createOperatingDay();
        $productId = $this->createProduct();
        $locationId = $this->createStockLocation();

        $this->assertTrue($this->fails([
            'payment_method' => 'cash',
            'operating_day_id' => $operatingDayId,
            'sold_at' => now()->toDateTimeString(),
            'stock_location_id' => $locationId,
            'items' => [['product_id' => $productId, 'quantity' => 1, 'voucher_id' => 9999]],
        ]));
    }
}
