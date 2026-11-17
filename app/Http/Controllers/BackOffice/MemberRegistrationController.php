<?php

namespace App\Http\Controllers\BackOffice;

use App\Exceptions\MemberRegistrationEmailVerificationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\UpdateMemberRegistrationAvsRequest;
use App\MemberRegistrationStatus;
use App\Models\Member;
use App\Models\MemberRegistration;
use App\Services\MemberRegistrationAvsConflictService;
use App\Services\MemberRegistrationPipelineService;
use App\Support\PiiMasker;
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
            new Middleware('permission:manage member registration', only: ['index', 'show', 'validate', 'retry', 'destroy', 'updateAvs']),
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

    public function show(
        MemberRegistration $memberRegistration,
        MemberRegistrationAvsConflictService $conflictService,
    ): Response {
        $memberRegistration->load('member');

        $canViewSensitiveData = Auth::user()?->can('view member sensitive data') ?? false;

        if ($canViewSensitiveData) {
            $memberRegistration->makeVisible(['form_data']);
        }

        return Inertia::render('backOffice/MemberRegistrations/Show', [
            'registration' => $memberRegistration,
            'formUrl' => $memberRegistration->formUrl(),
            'canViewSensitiveData' => $canViewSensitiveData,
            'canEditAvs' => $canViewSensitiveData,
            'avsConflict' => $this->avsConflictPayload(
                $conflictService->find($memberRegistration),
                $memberRegistration,
                $canViewSensitiveData,
            ),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $registration = MemberRegistration::create();

        return redirect()->route('bo.member-registrations.show', $registration)
            ->with('success', 'Formulaire créé avec succès.');
    }

    public function validate(
        MemberRegistration $memberRegistration,
        MemberRegistrationPipelineService $pipeline,
        MemberRegistrationAvsConflictService $conflictService,
    ): RedirectResponse {
        abort_unless(
            $memberRegistration->status === MemberRegistrationStatus::FORM_FILLED,
            403,
            'Seules les inscriptions au statut « en attente de validation » peuvent être validées.'
        );

        abort_unless(
            $memberRegistration->isEmailVerified(),
            403,
            "L'email du formulaire doit être vérifié avant de valider l'inscription."
        );

        if ($conflictService->find($memberRegistration)) {
            return redirect()->route('bo.member-registrations.show', $memberRegistration)
                ->with('error', 'Impossible de valider : le numéro AVS est déjà utilisé par un autre membre.');
        }

        $memberRegistration->error_message = null;
        $memberRegistration->failed_at = null;
        $memberRegistration->save();

        $pipeline->processFormSubmission($memberRegistration);

        return redirect()->route('bo.member-registrations.show', $memberRegistration)
            ->with('success', 'Inscription validée, traitement lancé.');
    }

    public function retry(
        MemberRegistration $memberRegistration,
        MemberRegistrationPipelineService $pipeline,
        MemberRegistrationAvsConflictService $conflictService,
    ): RedirectResponse {
        if (in_array($memberRegistration->status, [MemberRegistrationStatus::ERROR, MemberRegistrationStatus::MEMBER_CREATED], true)
            && $conflictService->find($memberRegistration)) {
            return redirect()->route('bo.member-registrations.show', $memberRegistration)
                ->with('error', 'Impossible de relancer : le numéro AVS est déjà utilisé par un autre membre.');
        }

        $memberRegistration->error_message = null;
        $memberRegistration->failed_at = null;
        $memberRegistration->save();

        try {
            $pipeline->retryFromStep($memberRegistration);
        } catch (\InvalidArgumentException|MemberRegistrationEmailVerificationException $e) {
            return redirect()->route('bo.member-registrations.show', $memberRegistration)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('bo.member-registrations.show', $memberRegistration)
            ->with('success', 'Traitement relancé avec succès.');
    }

    public function updateAvs(
        UpdateMemberRegistrationAvsRequest $request,
        MemberRegistration $memberRegistration,
    ): RedirectResponse {
        abort_unless(
            $memberRegistration->form_submitted_at !== null && $memberRegistration->member_id === null,
            403,
            'Le numéro AVS ne peut plus être corrigé à cette étape.'
        );

        $formData = $memberRegistration->form_data ?? [];
        $formData['avs'] = $request->validated('avs');

        $memberRegistration->form_data = $formData;
        $memberRegistration->error_message = null;
        $memberRegistration->failed_at = null;

        if ($memberRegistration->status === MemberRegistrationStatus::ERROR) {
            $memberRegistration->status = MemberRegistrationStatus::FORM_FILLED;
        }

        $memberRegistration->save();

        return redirect()->route('bo.member-registrations.show', $memberRegistration)
            ->with('success', 'Numéro AVS corrigé.');
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

    /** @return array<string, mixed>|null */
    private function avsConflictPayload(
        ?Member $member,
        MemberRegistration $registration,
        bool $canViewSensitiveData,
    ): ?array {
        if (! $member) {
            return null;
        }

        $avs = $registration->form_data['avs'] ?? null;

        return [
            'member_id' => $member->id,
            'member_name' => trim($member->first_name.' '.$member->last_name),
            'member_url' => Auth::user()?->can('manage members')
                ? route('bo.members.show', $member)
                : null,
            'avs' => $canViewSensitiveData ? $avs : PiiMasker::avs(is_string($avs) ? $avs : null),
        ];
    }
}
