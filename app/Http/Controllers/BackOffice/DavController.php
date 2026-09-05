<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\InfomaniakDavService;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class DavController extends Controller
{
    public function index(InfomaniakDavService $service)
    {
        try {
            $contacts = $service->getContacts();
        } catch (\Exception $e) {
            return Inertia::render('backOffice/Dav/Index', [
                'contacts' => [],
                'existingDavUids' => [],
                'error' => 'Erreur lors de la récupération des contacts: '.$e->getMessage(),
            ]);
        }

        $existingDavUids = Member::whereNotNull('dav_uid')->pluck('dav_uid')->toArray();

        return Inertia::render('backOffice/Dav/Index', [
            'contacts' => $contacts,
            'existingDavUids' => $existingDavUids,
        ]);
    }

    public function importFromDav(InfomaniakDavService $service)
    {
        try {
            $contacts = $service->getContacts();
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Erreur lors de la récupération des contacts: '.$e->getMessage()]);
        }

        $existingDavUids = Member::whereNotNull('dav_uid')->pluck('dav_uid')->flip()->toArray();

        $actifsContacts = array_filter($contacts, function ($contact) {
            return str_contains($contact['category'] ?? '', 'Actifs');
        });

        $toImport = array_filter($actifsContacts, function ($contact) use ($existingDavUids) {
            return ! isset($existingDavUids[$contact['uid']]);
        });

        $created = 0;

        foreach ($toImport as $contact) {
            $parts = explode(';', $contact['n'] ?? '');
            $lastName = $parts[0] ?? '';
            $firstName = $parts[1] ?? '';

            Member::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'dav_uid' => $contact['uid'],
            ]);

            $created++;
        }

        Log::info('Imported members from DAV contacts', ['count' => $created]);

        return back()->with('success', "$created membre(s) créé(s) avec succès.");
    }
}
