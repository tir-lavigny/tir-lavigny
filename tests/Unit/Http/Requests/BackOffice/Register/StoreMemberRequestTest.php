<?php

namespace Tests\Unit\Http\Requests\BackOffice\Register;

use App\Http\Requests\BackOffice\Register\StoreMemberRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreMemberRequestTest extends TestCase
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

    private function makeRequest(array $data): StoreMemberRequest
    {
        $request = new StoreMemberRequest;
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
        $request = new StoreMemberRequest;
        $request->setUserResolver(fn () => $this->user);

        $this->assertTrue($request->authorize());
    }

    public function test_authorize_fails_without_permission(): void
    {
        $user = User::factory()->create();
        $request = new StoreMemberRequest;
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }

    public function test_first_name_is_required(): void
    {
        $this->assertTrue($this->fails([
            'last_name' => 'Doe',
            'member_number' => '12345',
        ]));
    }

    public function test_last_name_is_required(): void
    {
        $this->assertTrue($this->fails([
            'first_name' => 'John',
            'member_number' => '12345',
        ]));
    }

    public function test_member_number_is_required(): void
    {
        $this->assertTrue($this->fails([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]));
    }

    public function test_member_number_must_be_unique(): void
    {
        DB::table('members')->insert([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'member_number' => '12345',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($this->fails([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'member_number' => '12345',
        ]));
    }

    public function test_passes_with_valid_data(): void
    {
        $this->assertTrue($this->passes([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'member_number' => '12345',
        ]));
    }
}
