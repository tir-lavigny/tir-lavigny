<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\DavServiceInterface;
use App\Services\DavSyncService;
use App\Services\MemberSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class DavController extends Controller
{
    public function index(DavServiceInterface $service): Response
    {
        try {
            $contacts = $service->getContacts();
        } catch (\Exception $e) {
            report($e);

            Log::error('DAV contacts fetch failed', [
                'operation' => 'index',
                'exception' => $e,
            ]);

            return Inertia::render('backOffice/Dav/Index', [
                'contacts' => [],
                'existingDavUids' => [],
                'error' => 'Erreur lors de la récupération des contacts: '.$e->getMessage(),
            ]);
        }

        $existingDavUids = Member::withTrashed()->whereNotNull('dav_uid')->pluck('dav_uid')->toArray();

        return Inertia::render('backOffice/Dav/Index', [
            'contacts' => $contacts,
            'existingDavUids' => $existingDavUids,
        ]);
    }

    public function importFromDav(DavSyncService $service): RedirectResponse
    {
        try {
            $result = $service->importFromDav();
        } catch (\Exception $e) {
            report($e);

            Log::error('DAV import failed', [
                'operation' => 'import',
                'exception' => $e,
            ]);

            return back()->withErrors(['error' => 'Erreur lors de la récupération des contacts: '.$e->getMessage()]);
        }

        return back()->with('success', "$result[created] membre(s) créé(s) avec succès.");
    }

    public function syncPreview(MemberSyncService $service): JsonResponse
    {
        try {
            $result = $service->preview();
        } catch (\Exception $e) {
            report($e);

            Log::error('DAV sync preview failed', [
                'operation' => 'sync_preview',
                'exception' => $e,
            ]);

            return response()->json(['error' => 'Erreur lors de la récupération des contacts: '.$e->getMessage()], 500);
        }

        return response()->json($result);
    }

    public function sync(MemberSyncService $service, Request $request): RedirectResponse
    {
        $choices = $request->validate([
            'choices' => 'required|array|min:1',
            'choices.*.member_id' => 'required|exists:members,id',
            'choices.*.fields' => 'required|array',
            'choices.*.fields.*' => 'required|in:member,dav',
        ]);

        $result = $service->apply($choices['choices']);

        return back()->with('success', "$result[updated] membre(s) synchronisé(s) avec succès.");
    }

    public function link(Request $request, MemberSyncService $service): RedirectResponse
    {
        $data = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'dav_uid' => ['required', 'string'],
        ]);

        $member = Member::withTrashed()->findOrFail($data['member_id']);
        $result = $service->link($member, $data['dav_uid']);

        if (! $result['success']) {
            return back()->with('error', $result['error']);
        }

        return back()->with('success', 'Contact lié avec succès.');
    }
}
