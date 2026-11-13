<?php

namespace Tests\Unit\Http\Requests\BackOffice;

use App\Http\Requests\BackOffice\UpdateMemberRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
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

    public function test_passes_with_empty_data(): void
    {
        $this->assertTrue($this->passes([]));
    }

    public function test_passes_with_valid_data(): void
    {
        $this->assertTrue($this->passes([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'status' => 'active',
        ]));
    }

    public function test_fails_with_invalid_email(): void
    {
        $this->assertFalse($this->passes([
            'email' => 'not-an-email',
        ]));
    }

    public function test_fails_with_invalid_status(): void
    {
        $this->assertFalse($this->passes([
            'status' => 'unknown',
        ]));
    }

    public function test_fails_with_invalid_canton(): void
    {
        $this->assertFalse($this->passes([
            'canton' => 'XX',
        ]));
    }
}
