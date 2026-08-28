<?php

namespace Tests\Unit\Http\Requests\API\Register;

use App\Http\Requests\API\Register\VoucherValidateRequest;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class VoucherValidateRequestTest extends TestCase
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

    private function makeRequest(array $data): VoucherValidateRequest
    {
        $request = new VoucherValidateRequest;
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

    public function test_code_is_required(): void
    {
        $this->assertTrue($this->fails([]));
    }

    public function test_product_id_is_required(): void
    {
        $this->assertTrue($this->fails(['code' => 'TEST']));
    }

    public function test_season_id_is_required(): void
    {
        $this->assertTrue($this->fails(['code' => 'TEST', 'product_id' => 1]));
    }

    public function test_code_must_exist_in_vouchers(): void
    {
        $productId = $this->createProduct();
        $seasonId = $this->createSeason();

        $this->assertTrue($this->fails([
            'code' => 'NONEXISTENT',
            'product_id' => $productId,
            'season_id' => $seasonId,
        ]));
    }

    public function test_product_id_must_exist(): void
    {
        $seasonId = $this->createSeason();

        $this->assertTrue($this->fails([
            'code' => 'TEST',
            'product_id' => 9999,
            'season_id' => $seasonId,
        ]));
    }

    public function test_season_id_must_exist(): void
    {
        $productId = $this->createProduct();

        $this->assertTrue($this->fails([
            'code' => 'TEST',
            'product_id' => $productId,
            'season_id' => 9999,
        ]));
    }

    public function test_passes_with_valid_usable_voucher(): void
    {
        $seasonId = $this->createSeason();
        $productId = $this->createProduct();
        $memberId = DB::table('members')->insertGetId([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'member_number' => uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('vouchers')->insert([
            'code' => 'VALIDCODE',
            'discount_percent' => 10,
            'product_id' => $productId,
            'season_id' => $seasonId,
            'member_id' => $memberId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($this->passes([
            'code' => 'VALIDCODE',
            'product_id' => $productId,
            'season_id' => $seasonId,
        ]));
    }

    public function test_authorize_passes_with_permission(): void
    {
        $request = new VoucherValidateRequest;
        $request->setUserResolver(fn () => $this->user);

        $this->assertTrue($request->authorize());
    }

    public function test_authorize_fails_without_permission(): void
    {
        $user = User::factory()->create();
        $request = new VoucherValidateRequest;
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }
}
