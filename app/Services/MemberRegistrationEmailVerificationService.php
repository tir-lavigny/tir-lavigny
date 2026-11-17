<?php

namespace App\Services;

use App\Exceptions\MemberRegistrationEmailVerificationException;
use App\Mail\MemberRegistrationEmailVerification;
use App\Models\MemberRegistration;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MemberRegistrationEmailVerificationService
{
    public const CODE_TTL_MINUTES = 15;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public function sendCode(MemberRegistration $registration): bool
    {
        $this->assertSubmitted($registration);

        if ($registration->isEmailVerified()) {
            return false;
        }

        $email = $registration->formEmail();

        if ($email === null) {
            throw new MemberRegistrationEmailVerificationException(
                "Aucune adresse email n'est associée à cette inscription."
            );
        }

        $wait = $this->secondsUntilResendAllowed($registration);

        if ($wait > 0) {
            throw new MemberRegistrationEmailVerificationException(
                "Veuillez patienter {$wait} secondes avant de demander un nouveau code."
            );
        }

        $code = (string) random_int(100000, 999999);

        $registration->email_verification_code_hash = Hash::make($code);
        $registration->email_verification_expires_at = now()->addMinutes(self::CODE_TTL_MINUTES);
        $registration->email_verification_attempts = 0;
        $registration->email_verification_sent_at = now();
        $registration->save();

        Mail::to($email)->send(new MemberRegistrationEmailVerification($registration, $code));

        Log::info('Member registration verification code sent', [
            'registration_id' => $registration->id,
        ]);

        return true;
    }

    public function verify(MemberRegistration $registration, string $code): void
    {
        $this->assertSubmitted($registration);

        if ($registration->isEmailVerified()) {
            throw new MemberRegistrationEmailVerificationException('Cet email est déjà vérifié.');
        }

        if ($registration->email_verification_code_hash === null) {
            throw new MemberRegistrationEmailVerificationException(
                'Aucun code de vérification actif. Demandez un nouveau code.'
            );
        }

        if ($registration->email_verification_expires_at === null
            || $registration->email_verification_expires_at->isPast()) {
            throw new MemberRegistrationEmailVerificationException(
                'Ce code a expiré. Demandez un nouveau code.'
            );
        }

        if ($registration->email_verification_attempts >= self::MAX_ATTEMPTS) {
            throw new MemberRegistrationEmailVerificationException(
                'Trop de tentatives. Demandez un nouveau code.'
            );
        }

        if (! Hash::check($code, $registration->email_verification_code_hash)) {
            $registration->email_verification_attempts++;
            $registration->save();

            $remaining = self::MAX_ATTEMPTS - $registration->email_verification_attempts;

            throw new MemberRegistrationEmailVerificationException(
                $remaining > 0
                    ? "Code incorrect. Il reste {$remaining} tentative(s)."
                    : 'Trop de tentatives. Demandez un nouveau code.'
            );
        }

        $registration->email_verified_at = now();
        $registration->email_verification_code_hash = null;
        $registration->email_verification_expires_at = null;
        $registration->email_verification_attempts = 0;
        $registration->save();

        Log::info('Member registration email verified', [
            'registration_id' => $registration->id,
        ]);
    }

    public function secondsUntilResendAllowed(MemberRegistration $registration): int
    {
        if ($registration->email_verification_sent_at === null) {
            return 0;
        }

        $allowedAt = $registration->email_verification_sent_at->copy()
            ->addSeconds(self::RESEND_COOLDOWN_SECONDS);

        return max(0, $allowedAt->getTimestamp() - now()->getTimestamp());
    }

    private function assertSubmitted(MemberRegistration $registration): void
    {
        if ($registration->form_submitted_at === null) {
            throw new MemberRegistrationEmailVerificationException(
                "Ce formulaire n'a pas encore été soumis."
            );
        }
    }
}
