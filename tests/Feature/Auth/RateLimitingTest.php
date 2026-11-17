<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_requests_are_rate_limited_per_ip(): void
    {
        Notification::fake();

        foreach (range(1, 3) as $attempt) {
            $this->post('/forgot-password', ['email' => "user{$attempt}@example.com"])
                ->assertStatus(302);
        }

        $this->post('/forgot-password', ['email' => 'user4@example.com'])
            ->assertStatus(429);
    }

    public function test_reset_password_requests_are_rate_limited_per_ip(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post('/reset-password', [
                'token' => 'invalid-token',
                'email' => "user{$attempt}@example.com",
                'password' => 'password',
                'password_confirmation' => 'password',
            ])->assertStatus(302);
        }

        $this->post('/reset-password', [
            'token' => 'invalid-token',
            'email' => 'user6@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(429);
    }

    public function test_password_confirmation_requests_are_rate_limited_per_user(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 6) as $attempt) {
            $this->actingAs($user)
                ->post('/confirm-password', ['password' => 'wrong-password'])
                ->assertStatus(302);
        }

        $this->actingAs($user)
            ->post('/confirm-password', ['password' => 'wrong-password'])
            ->assertStatus(429);
    }

    public function test_registration_requests_are_rate_limited_per_ip(): void
    {
        foreach (range(1, 3) as $attempt) {
            $this->post('/register', [
                'name' => "User {$attempt}",
                'email' => "user{$attempt}@example.com",
                'password' => 'password',
                'password_confirmation' => 'password',
            ])->assertStatus(404);
        }

        $this->post('/register', [
            'name' => 'User 4',
            'email' => 'user4@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(429);
    }
}
