<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\DavServiceInterface;
use App\Services\DavSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DavController extends Controller
{
    public function index(DavServiceInterface $service)
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

    public function importFromDav(DavSyncService $service)
    {
        try {
            $result = $service->importFromDav();
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Erreur lors de la récupération des contacts: '.$e->getMessage()]);
        }

        return back()->with('success', "$result[created] membre(s) créé(s) avec succès.");
    }

    public function syncPreview(DavSyncService $service): JsonResponse
    {
        try {
            $result = $service->syncPreview();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la récupération des contacts: '.$e->getMessage()], 500);
        }

        return response()->json($result);
    }

    public function sync(DavSyncService $service, Request $request)
    {
        $choices = $request->validate([
            'choices' => 'required|array|min:1',
            'choices.*.member_id' => 'required|exists:members,id',
            'choices.*.source' => 'required|in:member,dav',
        ]);

        $result = $service->sync($choices['choices']);

        return back()->with('success', "$result[updated] membre(s) synchronisé(s) avec succès.");
    }
}
