<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Models\MemberRegistration;
use App\Services\MemberRegistrationPipelineService;
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

    public function retry(MemberRegistration $memberRegistration, MemberRegistrationPipelineService $pipeline)
    {
        $memberRegistration->error_message = null;
        $memberRegistration->failed_at = null;
        $memberRegistration->save();

        $pipeline->retryFromStep($memberRegistration);

        return redirect()->route('bo.member-registrations.show', $memberRegistration)
            ->with('success', 'Traitement relancé avec succès.');
    }
}
