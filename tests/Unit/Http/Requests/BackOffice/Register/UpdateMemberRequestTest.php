<?php

namespace Tests\Unit\Http\Requests\BackOffice\Register;

use App\Http\Requests\BackOffice\Register\UpdateMemberRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateMemberRequestTest extends TestCase
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

    private function makeRequest(array $data, int $memberId = 1): UpdateMemberRequest
    {
        $request = new UpdateMemberRequest;
        $request->setUserResolver(fn () => $this->user);
        $request->merge($data);

        $route = new Route('PUT', "/members/{$memberId}", fn () => null);
        $route->bind($request);
        $route->setParameter('member', $memberId);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    private function passes(array $data, int $memberId = 1): bool
    {
        $request = $this->makeRequest($data, $memberId);
        $validator = Validator::make($request->all(), $request->rules());

        return $validator->passes();
    }

    private function fails(array $data, int $memberId = 1): bool
    {
        $request = $this->makeRequest($data, $memberId);
        $validator = Validator::make($request->all(), $request->rules());

        return $validator->fails();
    }

    public function test_authorize_passes_with_permission(): void
    {
        $request = new UpdateMemberRequest;
        $request->setUserResolver(fn () => $this->user);

        $this->assertTrue($request->authorize());
    }

    public function test_authorize_fails_without_permission(): void
    {
        $user = User::factory()->create();
        $request = new UpdateMemberRequest;
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }

    public function test_member_number_must_be_unique_except_self(): void
    {
        $memberId = DB::table('members')->insertGetId([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'member_number' => 'ORIGINAL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('members')->insert([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'member_number' => 'TAKEN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($this->fails(['member_number' => 'TAKEN'], $memberId));
    }

    public function test_member_number_can_keep_same_value(): void
    {
        $memberId = DB::table('members')->insertGetId([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'member_number' => 'ORIGINAL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($this->passes(['member_number' => 'ORIGINAL'], $memberId));
    }

    public function test_passes_with_empty_data(): void
    {
        $this->assertTrue($this->passes([]));
    }

    public function test_passes_with_valid_data(): void
    {
        $this->assertTrue($this->passes([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'member_number' => '99999',
        ]));
    }
}
