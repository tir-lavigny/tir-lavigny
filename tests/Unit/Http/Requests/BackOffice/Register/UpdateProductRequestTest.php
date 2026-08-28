<?php

namespace Tests\Unit\Http\Requests\BackOffice\Register;

use App\Http\Requests\BackOffice\Register\UpdateProductRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateProductRequestTest extends TestCase
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

    private function makeRequest(array $data): UpdateProductRequest
    {
        $request = new UpdateProductRequest;
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

    public function test_authorize_passes_with_permission(): void
    {
        $request = new UpdateProductRequest;
        $request->setUserResolver(fn () => $this->user);

        $this->assertTrue($request->authorize());
    }

    public function test_authorize_fails_without_permission(): void
    {
        $user = User::factory()->create();
        $request = new UpdateProductRequest;
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }

    public function test_name_must_be_string(): void
    {
        $this->assertTrue($this->fails(['name' => 123]));
    }

    public function test_name_max_length(): void
    {
        $this->assertTrue($this->fails(['name' => str_repeat('a', 256)]));
    }

    public function test_unit_price_must_be_numeric(): void
    {
        $this->assertTrue($this->fails(['unit_price' => 'abc']));
    }

    public function test_unit_price_must_be_non_negative(): void
    {
        $this->assertTrue($this->fails(['unit_price' => -1]));
    }

    public function test_category_id_must_exist(): void
    {
        $this->assertTrue($this->fails(['category_id' => 9999]));
    }

    public function test_allows_custom_price_must_be_boolean(): void
    {
        $this->assertTrue($this->fails(['allows_custom_price' => 'invalid']));
    }

    public function test_passes_with_empty_data(): void
    {
        $this->assertTrue($this->passes([]));
    }

    public function test_passes_with_valid_data(): void
    {
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Test Category',
            'is_restricted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($this->passes([
            'name' => 'Updated Product',
            'unit_price' => 20.00,
            'category_id' => $categoryId,
            'allows_custom_price' => false,
        ]));
    }
}
