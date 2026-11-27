<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered()
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_reset_password_does_not_reveal_whether_account_exists()
    {
        $user = User::factory()->create();

        $payload = [
            'token' => 'invalid-token',
            'password' => 'password',
            'password_confirmation' => 'password',
        ];

        $unknownUserResponse = $this->postJson('/reset-password', $payload + ['email' => 'unknown@example.com']);
        $knownUserResponse = $this->postJson('/reset-password', $payload + ['email' => $user->email]);

        $unknownUserResponse->assertStatus(422)->assertJsonPath('errors.email.0', __('passwords.token'));
        $knownUserResponse->assertStatus(422)->assertJsonPath('errors.email.0', __('passwords.token'));

        $this->assertSame($unknownUserResponse->json(), $knownUserResponse->json());
    }

    public function test_forgot_password_returns_generic_response_when_mail_fails()
    {
        $user = User::factory()->create();

        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('send')->andThrow(new TransportException('SMTP connection failed'));

        $mailFactory = Mockery::mock(MailFactory::class);
        $mailFactory->shouldReceive('mailer')->andReturn($mailer);

        $this->app->instance(MailFactory::class, $mailFactory);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status', __('A reset link will be sent if the account exists.'));
    }
}
