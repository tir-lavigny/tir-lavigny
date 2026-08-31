<?php

namespace Tests\Unit\Http\Requests\BackOffice\Register;

use App\Http\Requests\BackOffice\Register\StoreVoucherRequest;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreVoucherRequestTest extends TestCase
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

    private function makeRequest(array $data): StoreVoucherRequest
    {
        $request = new StoreVoucherRequest;
        $request->setUserResolver(fn () => $this->user);
        $request->merge($data);

        return $request;
    }

    private function passes(array $data): bool
    {
        request()->merge($data);
        $request = $this->makeRequest($data);
        $validator = Validator::make($request->all(), $request->rules());
        $request->withValidator($validator);

        return $validator->passes();
    }

    private function fails(array $data): bool
    {
        request()->merge($data);
        $request = $this->makeRequest($data);
        $validator = Validator::make($request->all(), $request->rules());
        $request->withValidator($validator);

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

    private function createSeason(): int
    {
        return DB::table('seasons')->insertGetId([
            'name' => 'Test Season',
            'start_date' => Carbon::now()->startOfYear()->toDateString(),
            'end_date' => Carbon::now()->endOfYear()->toDateString(),
            'initial_balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createProduct(): int
    {
        return DB::table('products')->insertGetId([
            'name' => 'Test Product',
            'unit_price' => 10.00,
            'allows_custom_price' => false,
            'category_id' => $this->createCategory(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createMember(): int
    {
        return DB::table('members')->insertGetId([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_authorize_passes_with_permission(): void
    {
        $request = new StoreVoucherRequest;
        $request->setUserResolver(fn () => $this->user);

        $this->assertTrue($request->authorize());
    }

    public function test_authorize_fails_without_permission(): void
    {
        $user = User::factory()->create();
        $request = new StoreVoucherRequest;
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }

    public function test_only_one_discount_type_provided(): void
    {
        $this->assertTrue($this->fails([
            'discount_percent' => 10,
            'discount_fixed' => 5.00,
            'product_id' => 1,
            'season_id' => 1,
            'member_id' => 1,
        ]));
    }

    public function test_at_least_one_discount_type_required(): void
    {
        $this->assertTrue($this->fails([
            'product_id' => 1,
            'season_id' => 1,
            'member_id' => 1,
        ]));
    }

    public function test_product_id_is_required(): void
    {
        $this->assertTrue($this->fails([
            'discount_percent' => 10,
            'season_id' => 1,
            'member_id' => 1,
        ]));
    }

    public function test_season_id_is_required(): void
    {
        $this->assertTrue($this->fails([
            'discount_percent' => 10,
            'product_id' => 1,
            'member_id' => 1,
        ]));
    }

    public function test_member_id_is_required(): void
    {
        $this->assertTrue($this->fails([
            'discount_percent' => 10,
            'product_id' => 1,
            'season_id' => 1,
        ]));
    }

    public function test_passes_with_percent_discount(): void
    {
        $productId = $this->createProduct();
        $seasonId = $this->createSeason();
        $memberId = $this->createMember();

        $this->assertTrue($this->passes([
            'discount_percent' => 10,
            'product_id' => $productId,
            'season_id' => $seasonId,
            'member_id' => $memberId,
        ]));
    }

    public function test_passes_with_fixed_discount(): void
    {
        $productId = $this->createProduct();
        $seasonId = $this->createSeason();
        $memberId = $this->createMember();

        $this->assertTrue($this->passes([
            'discount_fixed' => 5.00,
            'product_id' => $productId,
            'season_id' => $seasonId,
            'member_id' => $memberId,
        ]));
    }

    public function test_discount_percent_must_be_between_0_and_100(): void
    {
        $this->assertTrue($this->fails([
            'discount_percent' => 101,
            'product_id' => 1,
            'season_id' => 1,
            'member_id' => 1,
        ]));
    }

    public function test_discount_fixed_must_be_non_negative(): void
    {
        $this->assertTrue($this->fails([
            'discount_fixed' => -1,
            'product_id' => 1,
            'season_id' => 1,
            'member_id' => 1,
        ]));
    }
}
