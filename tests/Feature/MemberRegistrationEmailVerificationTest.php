<?php

namespace Tests\Feature;

use App\Mail\MemberRegistrationEmailVerification;
use App\Mail\MemberRegistrationValidationRequested;
use App\Models\MemberRegistration;
use App\Models\User;
use App\Services\MemberRegistrationEmailVerificationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MemberRegistrationEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('get member registration mails');
    }

    /** @return array<string, mixed> */
    private function formData(MemberRegistration $registration): array
    {
        return [
            'form_uuid' => (string) $registration->form_uuid,
            'last_name' => 'Dupont',
            'first_name' => 'Jean',
            'email' => 'jean.dupont@example.com',
            'is_swiss_citizen' => true,
            'avs' => '756.1234.5678.90',
            'tel' => '+41 21 123 45 67',
            'street' => 'Rue du Marché 1',
            'npa' => '1003',
            'city' => 'Lausanne',
            'canton' => 'VD',
            'country' => 'Suisse',
        ];
    }

    private function submittedRegistration(): MemberRegistration
    {
        Mail::fake();

        $registration = MemberRegistration::create();

        $this->post(route('member-registration.store'), $this->formData($registration))
            ->assertRedirect(route('member-registration.show', $registration->form_uuid));

        return $registration->refresh();
    }

    private function sentCode(): string
    {
        $code = null;

        Mail::assertSent(MemberRegistrationEmailVerification::class, function ($mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });

        $this->assertIsString($code);

        return $code;
    }

    public function test_submission_sends_verification_code_instead_of_validation_request(): void
    {
        $registration = $this->submittedRegistration();

        Mail::assertSent(MemberRegistrationEmailVerification::class, function ($mail): bool {
            return $mail->hasTo('jean.dupont@example.com');
        });
        Mail::assertNotSent(MemberRegistrationValidationRequested::class);

        $this->assertNull($registration->email_verified_at);
        $this->assertNotNull($registration->email_verification_code_hash);
        $this->assertNotNull($registration->email_verification_expires_at);
        $this->assertStringNotContainsString($this->sentCode(), $registration->email_verification_code_hash);
    }

    public function test_verification_email_contains_code_in_html_and_text_parts(): void
    {
        $this->submittedRegistration();

        Mail::assertSent(MemberRegistrationEmailVerification::class, function (MemberRegistrationEmailVerification $mail): bool {
            $mail->assertSeeInHtml($mail->code);
            $mail->assertSeeInText($mail->code);

            return true;
        });
    }

    public function test_correct_code_verifies_email_and_notifies_admins(): void
    {
        $registration = $this->submittedRegistration();

        $response = $this->post(route('member-registration.verify'), [
            'form_uuid' => $registration->form_uuid,
            'code' => $this->sentCode(),
        ]);

        $response->assertRedirect(route('member-registration.show', $registration->form_uuid));

        $registration->refresh();
        $this->assertNotNull($registration->email_verified_at);
        $this->assertNull($registration->email_verification_code_hash);
        $this->assertNull($registration->email_verification_expires_at);

        Mail::assertSent(MemberRegistrationValidationRequested::class, function ($mail): bool {
            return $mail->hasTo($this->admin->email);
        });
    }

    public function test_wrong_code_increments_attempts(): void
    {
        $registration = $this->submittedRegistration();
        $registration->email_verification_code_hash = Hash::make('123456');
        $registration->email_verification_expires_at = now()->addMinutes(15);
        $registration->email_verification_attempts = 0;
        $registration->save();

        $response = $this->post(route('member-registration.verify'), [
            'form_uuid' => $registration->form_uuid,
            'code' => '654321',
        ]);

        $response->assertSessionHasErrors('code');

        $registration->refresh();
        $this->assertSame(1, $registration->email_verification_attempts);
        $this->assertNull($registration->email_verified_at);
        Mail::assertNotSent(MemberRegistrationValidationRequested::class);
    }

    public function test_code_is_rejected_after_max_attempts(): void
    {
        $registration = $this->submittedRegistration();
        $registration->email_verification_code_hash = Hash::make('123456');
        $registration->email_verification_expires_at = now()->addMinutes(15);
        $registration->email_verification_attempts = MemberRegistrationEmailVerificationService::MAX_ATTEMPTS;
        $registration->save();

        $response = $this->post(route('member-registration.verify'), [
            'form_uuid' => $registration->form_uuid,
            'code' => '123456',
        ]);

        $response->assertSessionHasErrors('code');

        $this->assertNull($registration->refresh()->email_verified_at);
    }

    public function test_expired_code_is_rejected(): void
    {
        $registration = $this->submittedRegistration();
        $registration->email_verification_code_hash = Hash::make('123456');
        $registration->email_verification_expires_at = now()->subMinute();
        $registration->save();

        $response = $this->post(route('member-registration.verify'), [
            'form_uuid' => $registration->form_uuid,
            'code' => '123456',
        ]);

        $response->assertSessionHasErrors('code');

        $this->assertNull($registration->refresh()->email_verified_at);
    }

    public function test_verify_requires_a_six_digit_code(): void
    {
        $registration = $this->submittedRegistration();

        $response = $this->post(route('member-registration.verify'), [
            'form_uuid' => $registration->form_uuid,
            'code' => '12ab',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_resend_is_blocked_during_cooldown(): void
    {
        $registration = $this->submittedRegistration();

        $response = $this->post(route('member-registration.resend-verification'), [
            'form_uuid' => $registration->form_uuid,
        ]);

        $response->assertSessionHasErrors('code');

        $errors = session('errors');
        $this->assertStringContainsString('patienter', $errors->first('code'));
        Mail::assertSent(MemberRegistrationEmailVerification::class, 1);
    }

    public function test_resend_is_throttled_per_registration(): void
    {
        $registration = $this->submittedRegistration();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->travel(61)->seconds();

            $this->post(route('member-registration.resend-verification'), [
                'form_uuid' => $registration->form_uuid,
            ])->assertSessionHasNoErrors();
        }

        $this->travel(61)->seconds();

        $response = $this->post(route('member-registration.resend-verification'), [
            'form_uuid' => $registration->form_uuid,
        ]);

        $response->assertSessionHasErrors('code');

        $errors = session('errors');
        $this->assertStringContainsString('Trop de codes', $errors->first('code'));
        Mail::assertSent(MemberRegistrationEmailVerification::class, 6);
    }

    public function test_show_page_exposes_verification_state_without_leaking_email(): void
    {
        $registration = $this->submittedRegistration();

        $response = $this->get(route('member-registration.show', $registration->form_uuid));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('MemberRegistration')
            ->where('submitted', true)
            ->where('verified', false)
            ->where('maskedEmail', 'j***@example.com')
            ->missing('registration.form_data')
        );
    }

    public function test_verified_registration_cannot_be_verified_again(): void
    {
        $registration = $this->submittedRegistration();
        $registration->email_verified_at = now();
        $registration->save();

        $response = $this->post(route('member-registration.verify'), [
            'form_uuid' => $registration->form_uuid,
            'code' => '123456',
        ]);

        $response->assertSessionHasErrors('code');
    }
}
