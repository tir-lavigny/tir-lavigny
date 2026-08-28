<?php

namespace Tests\Unit\Http\Requests\BackOffice\Register;

use App\Http\Requests\BackOffice\Register\UpdateCategoryRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateCategoryRequestTest extends TestCase
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

    private function makeRequest(array $data, int $categoryId = 1): UpdateCategoryRequest
    {
        $request = new UpdateCategoryRequest;
        $request->setUserResolver(fn () => $this->user);
        $request->merge($data);

        $route = new Route('PUT', "/categories/{$categoryId}", fn () => null);
        $route->bind($request);
        $route->setParameter('category', $categoryId);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    private function passes(array $data, int $categoryId = 1): bool
    {
        $request = $this->makeRequest($data, $categoryId);
        $validator = Validator::make($request->all(), $request->rules());

        return $validator->passes();
    }

    private function fails(array $data, int $categoryId = 1): bool
    {
        $request = $this->makeRequest($data, $categoryId);
        $validator = Validator::make($request->all(), $request->rules());

        return $validator->fails();
    }

    public function test_authorize_passes_with_permission(): void
    {
        $request = new UpdateCategoryRequest;
        $request->setUserResolver(fn () => $this->user);

        $this->assertTrue($request->authorize());
    }

    public function test_authorize_fails_without_permission(): void
    {
        $user = User::factory()->create();
        $request = new UpdateCategoryRequest;
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }

    public function test_name_must_be_unique_except_self(): void
    {
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Original',
            'is_restricted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('categories')->insert([
            'name' => 'Taken',
            'is_restricted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($this->fails(['name' => 'Taken'], $categoryId));
    }

    public function test_name_can_keep_same_value(): void
    {
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Original',
            'is_restricted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($this->passes(['name' => 'Original'], $categoryId));
    }

    public function test_is_restricted_must_be_boolean(): void
    {
        $this->assertTrue($this->fails(['is_restricted' => 'invalid']));
    }

    public function test_passes_with_empty_data(): void
    {
        $this->assertTrue($this->passes([]));
    }
}
