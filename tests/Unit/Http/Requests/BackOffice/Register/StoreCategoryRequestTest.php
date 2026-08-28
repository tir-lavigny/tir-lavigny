<?php

namespace Tests\Unit\Http\Requests\BackOffice\Register;

use App\Http\Requests\BackOffice\Register\StoreCategoryRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreCategoryRequestTest extends TestCase
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

    private function makeRequest(array $data): StoreCategoryRequest
    {
        $request = new StoreCategoryRequest;
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
        $request = new StoreCategoryRequest;
        $request->setUserResolver(fn () => $this->user);

        $this->assertTrue($request->authorize());
    }

    public function test_authorize_fails_without_permission(): void
    {
        $user = User::factory()->create();
        $request = new StoreCategoryRequest;
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }

    public function test_name_is_required(): void
    {
        $this->assertTrue($this->fails([]));
    }

    public function test_name_must_be_unique(): void
    {
        DB::table('categories')->insert([
            'name' => 'Existing Category',
            'is_restricted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($this->fails(['name' => 'Existing Category']));
    }

    public function test_name_max_length(): void
    {
        $this->assertTrue($this->fails(['name' => str_repeat('a', 256)]));
    }

    public function test_is_restricted_must_be_boolean(): void
    {
        $this->assertTrue($this->fails([
            'name' => 'New Category',
            'is_restricted' => 'invalid',
        ]));
    }

    public function test_passes_with_valid_data(): void
    {
        $this->assertTrue($this->passes([
            'name' => 'New Category',
            'is_restricted' => true,
        ]));
    }

    public function test_passes_with_default_is_restricted(): void
    {
        $this->assertTrue($this->passes([
            'name' => 'New Category',
        ]));
    }
}
