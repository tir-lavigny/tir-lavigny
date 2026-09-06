<?php

namespace App\Http\Controllers\BackOffice;

use App\Events\MemberRegistrationCompleted;
use App\Events\MemberRegistrationDavCreated;
use App\Events\MemberRegistrationFormSubmitted;
use App\Http\Controllers\Controller;
use App\MemberRegistrationStatus;
use App\Models\MemberRegistration;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class MemberRegistrationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:create member registration', only: ['store']),
            new Middleware('permission:manage member registration', only: ['index', 'show', 'retry']),
        ];
    }

    public function index(Request $request)
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

    public function show(MemberRegistration $memberRegistration)
    {
        $memberRegistration->load('member');

        return Inertia::render('backOffice/MemberRegistrations/Show', [
            'registration' => $memberRegistration,
            'formUrl' => $memberRegistration->formUrl(),
        ]);
    }

    public function store(Request $request)
    {
        $registration = MemberRegistration::create();

        return redirect()->route('bo.member-registrations.show', $registration)
            ->with('success', 'Formulaire créé avec succès.');
    }

    public function retry(MemberRegistration $registration)
    {
        $registration->error_message = null;
        $registration->failed_at = null;
        $registration->save();

        match ($registration->status) {
            MemberRegistrationStatus::FORM_FILLED => MemberRegistrationFormSubmitted::dispatch($registration),
            MemberRegistrationStatus::DAV_CREATED => MemberRegistrationDavCreated::dispatch($registration),
            MemberRegistrationStatus::MEMBER_CREATED => MemberRegistrationCompleted::dispatch($registration),
            default => abort(422, 'Aucune étape à réessayer.'),
        };

        return redirect()->route('bo.member-registrations.show', $registration)
            ->with('success', 'Traitement relancé avec succès.');
    }
}
