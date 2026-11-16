<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\MemberRegistrationStatus;
use App\Models\MemberRegistration;
use App\Services\MemberRegistrationPipelineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MemberRegistrationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:create member registration', only: ['store']),
            new Middleware('permission:manage member registration', only: ['index', 'show', 'validate', 'retry', 'destroy']),
        ];
    }

    public function index(Request $request): Response
    {
        $query = MemberRegistration::with('member');

        $status = $request->input('status');
        if ($status) {
            $query->where('status', $status);
        }

        $query->orderBy('created_at', 'desc');

        $registrations = $query->paginate(15)->withQueryString();

        return Inertia::render('backOffice/MemberRegistrations/Index', [
            'registrations' => $registrations,
            'filters' => [
                'status' => $status,
            ],
        ]);
    }

    public function show(MemberRegistration $memberRegistration): Response
    {
        $memberRegistration->load('member');

        $canViewSensitiveData = Auth::user()?->can('view member sensitive data') ?? false;

        if ($canViewSensitiveData) {
            $memberRegistration->makeVisible(['form_data']);
        }

        return Inertia::render('backOffice/MemberRegistrations/Show', [
            'registration' => $memberRegistration,
            'formUrl' => $memberRegistration->formUrl(),
            'canViewSensitiveData' => $canViewSensitiveData,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $registration = MemberRegistration::create();

        return redirect()->route('bo.member-registrations.show', $registration)
            ->with('success', 'Formulaire créé avec succès.');
    }

    public function validate(MemberRegistration $memberRegistration, MemberRegistrationPipelineService $pipeline): RedirectResponse
    {
        abort_unless(
            $memberRegistration->status === MemberRegistrationStatus::FORM_FILLED,
            403,
            'Seules les inscriptions au statut « en attente de validation » peuvent être validées.'
        );

        $memberRegistration->error_message = null;
        $memberRegistration->failed_at = null;
        $memberRegistration->save();

        $pipeline->processFormSubmission($memberRegistration);

        return redirect()->route('bo.member-registrations.show', $memberRegistration)
            ->with('success', 'Inscription validée, traitement lancé.');
    }

    public function retry(MemberRegistration $memberRegistration, MemberRegistrationPipelineService $pipeline): RedirectResponse
    {
        $memberRegistration->error_message = null;
        $memberRegistration->failed_at = null;
        $memberRegistration->save();

        try {
            $pipeline->retryFromStep($memberRegistration);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('bo.member-registrations.show', $memberRegistration)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('bo.member-registrations.show', $memberRegistration)
            ->with('success', 'Traitement relancé avec succès.');
    }

    public function destroy(MemberRegistration $memberRegistration): RedirectResponse
    {
        abort_unless(
            $memberRegistration->form_submitted_at === null,
            403,
            'Seules les inscriptions sans formulaire soumis peuvent être supprimées.'
        );

        $memberRegistration->delete();

        return redirect()->route('bo.member-registrations.index')
            ->with('success', 'Lien supprimé avec succès.');
    }
}
