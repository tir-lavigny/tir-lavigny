<?php

namespace App\Http\Controllers;

use App\Events\MemberRegistrationEmailVerified;
use App\Events\MemberRegistrationFormSubmitted;
use App\Exceptions\MemberRegistrationEmailVerificationException;
use App\Http\Requests\StoreMemberRegistrationRequest;
use App\Http\Requests\VerifyMemberRegistrationEmailRequest;
use App\MemberRegistrationStatus;
use App\Models\MemberRegistration;
use App\Services\MemberRegistrationEmailVerificationService;
use App\Support\PiiMasker;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class MemberRegistrationController extends Controller
{
    public function show(
        string $form_uuid,
        MemberRegistrationEmailVerificationService $verification,
    ): Response {
        $registration = MemberRegistration::where('form_uuid', $form_uuid)->firstOrFail();

        if ($registration->isEmailVerified()
            && $registration->form_submitted_at
            && $registration->form_submitted_at->diffInHours(now()) > 24) {
            abort(404);
        }

        $submitted = $registration->form_submitted_at !== null;

        if ($submitted) {
            $registration->makeHidden(['form_name', 'error_message', 'failed_at']);
        }

        return Inertia::render('MemberRegistration', [
            'registration' => $registration,
            'formUrl' => $registration->formUrl(),
            'submitted' => $submitted,
            'verified' => $registration->isEmailVerified(),
            'maskedEmail' => PiiMasker::email($registration->formEmail()),
            'resendAvailableIn' => $registration->isEmailVerified()
                ? 0
                : $verification->secondsUntilResendAllowed($registration),
            'og' => [
                'title' => config('app.name'),
                'description' => 'Formulaire d\'inscription pour devenir membre de la Société de tir de Lavigny.',
                'image' => asset('/images/hero.jpg'),
                'type' => 'website',
            ],
        ]);
    }

    public function store(StoreMemberRegistrationRequest $request): RedirectResponse
    {
        $registration = MemberRegistration::where('form_uuid', $request->validated('form_uuid'))->firstOrFail();

        if ($registration->form_submitted_at) {
            abort(403, 'Ce formulaire a déjà été soumis.');
        }

        $formData = collect($request->validated())->except('form_uuid')->toArray();

        $registration->form_data = $formData;
        $registration->form_submitted_at = Carbon::now('Europe/Zurich')->utc();
        $registration->status = MemberRegistrationStatus::FORM_FILLED;
        $registration->save();

        try {
            MemberRegistrationFormSubmitted::dispatch($registration);
        } catch (\Throwable $e) {
            report($e);

            Log::error('Member registration submission dispatch failed', [
                'registration_id' => $registration->id,
                'status' => $registration->status->value,
                'exception' => $e,
            ]);
        }

        return redirect()->route('member-registration.show', $registration->form_uuid)
            ->with('success', 'Votre formulaire a été soumis. Vérifiez votre email pour le valider.');
    }

    public function verify(
        VerifyMemberRegistrationEmailRequest $request,
        MemberRegistrationEmailVerificationService $verification,
    ): RedirectResponse {
        $registration = MemberRegistration::where('form_uuid', $request->validated('form_uuid'))->firstOrFail();

        try {
            $verification->verify($registration, $request->validated('code'));
        } catch (MemberRegistrationEmailVerificationException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        MemberRegistrationEmailVerified::dispatch($registration);

        return redirect()->route('member-registration.show', $registration->form_uuid)
            ->with('success', 'Votre email a été vérifié. Nous traiterons votre inscription prochainement.');
    }

    public function resendVerification(
        Request $request,
        MemberRegistrationEmailVerificationService $verification,
    ): RedirectResponse {
        $validated = $request->validate([
            'form_uuid' => ['required', 'string', 'exists:member_registrations,form_uuid'],
        ]);

        $registration = MemberRegistration::where('form_uuid', $validated['form_uuid'])->firstOrFail();

        try {
            $sent = $verification->sendCode($registration);
        } catch (MemberRegistrationEmailVerificationException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            Log::error('Member registration verification email failed', [
                'registration_id' => $registration->id,
                'exception' => $e,
            ]);

            return back()->withErrors([
                'code' => "L'envoi du code a échoué. Veuillez réessayer plus tard.",
            ]);
        }

        return back()->with(
            'success',
            $sent
                ? 'Un nouveau code de vérification vous a été envoyé par email.'
                : 'Cet email est déjà vérifié.'
        );
    }
}
