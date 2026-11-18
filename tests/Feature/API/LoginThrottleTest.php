<?php

namespace Tests\Feature\API;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_login_is_rate_limited_per_ip(): void
    {
        foreach (range(1, 10) as $attempt) {
            $this->postJson(route('api.login'), [
                'email' => "user{$attempt}@example.com",
                'password' => 'incorrect',
            ])->assertStatus(401);
        }

        $this->postJson(route('api.login'), [
            'email' => 'user11@example.com',
            'password' => 'incorrect',
        ])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_login_is_rate_limited_per_account(): void
    {
        $user = AuthTestHelper::mockUser();

        foreach (range(1, 5) as $attempt) {
            $this->postJson(route('api.login'), [
                'email' => $user->email,
                'password' => 'incorrect',
            ])->assertStatus(401);
        }

        $this->postJson(route('api.login'), [
            'email' => $user->email,
            'password' => 'incorrect',
        ])
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        AuthTestHelper::clearUser($user);
    }
}
