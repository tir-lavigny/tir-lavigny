<?php

namespace Tests\Unit\Http\Requests\BackOffice\Register;

use App\Http\Requests\BackOffice\Register\StoreProductRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreProductRequestTest extends TestCase
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

    private function makeRequest(array $data): StoreProductRequest
    {
        $request = new StoreProductRequest;
        $request->setUserResolver(fn () => $this->user);
        $request->merge($data);

        return $request;
    }

    private function passes(array $data): bool
    {
        $request = $this->makeRequest($data);
        $validator = Validator::make($request->all(), $request->rules());

        return $validator->passes();
    }

    private function fails(array $data): bool
    {
        $request = $this->makeRequest($data);
        $validator = Validator::make($request->all(), $request->rules());

        return $validator->fails();
    }

    private function createCategory(): int
    {
        return DB::table('categories')->insertGetId([
            'name' => 'Test Category',
            'is_restricted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_authorize_passes_with_permission(): void
    {
        $request = new StoreProductRequest;
        $request->setUserResolver(fn () => $this->user);

        $this->assertTrue($request->authorize());
    }

    public function test_authorize_fails_without_permission(): void
    {
        $user = User::factory()->create();
        $request = new StoreProductRequest;
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }

    public function test_name_is_required(): void
    {
        $categoryId = $this->createCategory();

        $this->assertTrue($this->fails([
            'unit_price' => 10.00,
            'category_id' => $categoryId,
        ]));
    }

    public function test_name_max_length(): void
    {
        $categoryId = $this->createCategory();

        $this->assertTrue($this->fails([
            'name' => str_repeat('a', 256),
            'unit_price' => 10.00,
            'category_id' => $categoryId,
        ]));
    }

    public function test_unit_price_is_required(): void
    {
        $categoryId = $this->createCategory();

        $this->assertTrue($this->fails([
            'name' => 'Test Product',
            'category_id' => $categoryId,
        ]));
    }

    public function test_unit_price_must_be_numeric(): void
    {
        $categoryId = $this->createCategory();

        $this->assertTrue($this->fails([
            'name' => 'Test Product',
            'unit_price' => 'abc',
            'category_id' => $categoryId,
        ]));
    }

    public function test_unit_price_must_be_non_negative(): void
    {
        $categoryId = $this->createCategory();

        $this->assertTrue($this->fails([
            'name' => 'Test Product',
            'unit_price' => -1,
            'category_id' => $categoryId,
        ]));
    }

    public function test_category_id_is_required(): void
    {
        $this->assertTrue($this->fails([
            'name' => 'Test Product',
            'unit_price' => 10.00,
        ]));
    }

    public function test_category_id_must_exist(): void
    {
        $this->assertTrue($this->fails([
            'name' => 'Test Product',
            'unit_price' => 10.00,
            'category_id' => 9999,
        ]));
    }

    public function test_allows_custom_price_must_be_boolean(): void
    {
        $categoryId = $this->createCategory();

        $this->assertTrue($this->fails([
            'name' => 'Test Product',
            'unit_price' => 10.00,
            'category_id' => $categoryId,
            'allows_custom_price' => 'invalid',
        ]));
    }

    public function test_passes_with_valid_data(): void
    {
        $categoryId = $this->createCategory();

        $this->assertTrue($this->passes([
            'name' => 'Test Product',
            'unit_price' => 10.00,
            'category_id' => $categoryId,
            'allows_custom_price' => true,
        ]));
    }

    public function test_picture_must_be_image(): void
    {
        $categoryId = $this->createCategory();

        $this->assertTrue($this->fails([
            'name' => 'Test Product',
            'unit_price' => 10.00,
            'category_id' => $categoryId,
            'picture' => 'not-an-image',
        ]));
    }
}
