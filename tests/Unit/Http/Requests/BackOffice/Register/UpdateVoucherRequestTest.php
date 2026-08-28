<?php

namespace Tests\Unit\Http\Requests\BackOffice\Register;

use App\Http\Requests\BackOffice\Register\UpdateVoucherRequest;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateVoucherRequestTest extends TestCase
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

    private function makeRequest(array $data, int $voucherId = 1): UpdateVoucherRequest
    {
        $request = new UpdateVoucherRequest;
        $request->setUserResolver(fn () => $this->user);
        $request->merge($data);

        $route = new Route('PUT', "/vouchers/{$voucherId}", fn () => null);
        $route->bind($request);
        $route->setParameter('voucher', $voucherId);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    private function passes(array $data, int $voucherId = 1): bool
    {
        request()->merge($data);
        $request = $this->makeRequest($data, $voucherId);
        $validator = Validator::make($request->all(), $request->rules());
        $request->withValidator($validator);

        return $validator->passes();
    }

    private function fails(array $data, int $voucherId = 1): bool
    {
        request()->merge($data);
        $request = $this->makeRequest($data, $voucherId);
        $validator = Validator::make($request->all(), $request->rules());
        $request->withValidator($validator);

        return $validator->fails();
    }

    public function test_authorize_passes_with_permission(): void
    {
        $request = new UpdateVoucherRequest;
        $request->setUserResolver(fn () => $this->user);

        $this->assertTrue($request->authorize());
    }

    public function test_authorize_fails_without_permission(): void
    {
        $user = User::factory()->create();
        $request = new UpdateVoucherRequest;
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }

    public function test_code_must_be_unique_except_self(): void
    {
        $productId = DB::table('products')->insertGetId([
            'name' => 'Test Product',
            'unit_price' => 10.00,
            'allows_custom_price' => false,
            'category_id' => DB::table('categories')->insertGetId([
                'name' => 'Test Category',
                'is_restricted' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $seasonId = DB::table('seasons')->insertGetId([
            'name' => 'Test Season',
            'start_date' => Carbon::now()->startOfYear()->toDateString(),
            'end_date' => Carbon::now()->endOfYear()->toDateString(),
            'initial_balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $memberId = DB::table('members')->insertGetId([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'member_number' => uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $voucherId = DB::table('vouchers')->insertGetId([
            'code' => 'ORIGINAL',
            'discount_percent' => 10,
            'product_id' => $productId,
            'season_id' => $seasonId,
            'member_id' => $memberId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('vouchers')->insert([
            'code' => 'TAKEN',
            'discount_percent' => 10,
            'product_id' => $productId,
            'season_id' => $seasonId,
            'member_id' => $memberId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($this->fails(['code' => 'TAKEN'], $voucherId));
    }

    public function test_code_can_keep_same_value(): void
    {
        $productId = DB::table('products')->insertGetId([
            'name' => 'Test Product',
            'unit_price' => 10.00,
            'allows_custom_price' => false,
            'category_id' => DB::table('categories')->insertGetId([
                'name' => 'Test Category',
                'is_restricted' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $seasonId = DB::table('seasons')->insertGetId([
            'name' => 'Test Season',
            'start_date' => Carbon::now()->startOfYear()->toDateString(),
            'end_date' => Carbon::now()->endOfYear()->toDateString(),
            'initial_balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $memberId = DB::table('members')->insertGetId([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'member_number' => uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $voucherId = DB::table('vouchers')->insertGetId([
            'code' => 'ORIGINAL',
            'discount_percent' => 10,
            'product_id' => $productId,
            'season_id' => $seasonId,
            'member_id' => $memberId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($this->passes(['code' => 'ORIGINAL'], $voucherId));
    }

    public function test_only_one_discount_type_provided(): void
    {
        $this->assertTrue($this->fails([
            'discount_percent' => 10,
            'discount_fixed' => 5.00,
        ]));
    }

    public function test_passes_with_empty_data(): void
    {
        $this->assertTrue($this->passes([]));
    }
}
