<?php

namespace App\Http\Controllers;

use App\Events\MemberRegistrationFormSubmitted;
use App\Http\Requests\StoreMemberRegistrationRequest;
use App\MemberRegistrationStatus;
use App\Models\MemberRegistration;
use Inertia\Inertia;

class MemberRegistrationController extends Controller
{
    public function show(string $form_uuid)
    {
        $registration = MemberRegistration::where('form_uuid', $form_uuid)->firstOrFail();

        if ($registration->form_submitted_at && $registration->form_submitted_at->diffInHours(now()) > 24) {
            abort(404);
        }

        $submitted = $registration->form_submitted_at !== null;

        return Inertia::render('MemberRegistration', [
            'registration' => $registration,
            'formUrl' => $registration->formUrl(),
            'submitted' => $submitted,
            'og' => [
                'title' => config('app.name'),
                'description' => 'Formulaire d\'inscription pour devenir membre de la Société de tir de Lavigny.',
                'image' => asset('/images/hero.jpg'),
                'type' => 'website',
            ],
        ]);
    }

    public function store(StoreMemberRegistrationRequest $request)
    {
        $registration = MemberRegistration::where('form_uuid', $request->validated('form_uuid'))->firstOrFail();

        if ($registration->form_submitted_at) {
            abort(403, 'Ce formulaire a déjà été soumis.');
        }

        $formData = collect($request->validated())->except('form_uuid')->toArray();

        $registration->form_data = $formData;
        $registration->form_submitted_at = now();
        $registration->status = MemberRegistrationStatus::FORM_FILLED;
        $registration->save();

        MemberRegistrationFormSubmitted::dispatch($registration);

        return redirect()->route('member-registration.show', $registration->form_uuid)
            ->with('success', 'Votre formulaire a été soumis avec succès.');
    }
}
