<?php

namespace App\Http\Controllers\BackOffice;

use App\DavGroupLabels;
use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\DavServiceInterface;
use App\Services\DavSyncService;
use App\Services\MemberDavSyncFailureNotifier;
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
                'activeCategory' => DavGroupLabels::ACTIVE_MEMBERS->value,
                'passiveCategory' => DavGroupLabels::PASSIVE_MEMBERS->value,
                'error' => 'Erreur lors de la récupération des contacts.',
            ]);
        }

        $existingDavUids = Member::withTrashed()->whereNotNull('dav_uid')->pluck('dav_uid')->toArray();

        return Inertia::render('backOffice/Dav/Index', [
            'contacts' => $contacts,
            'existingDavUids' => $existingDavUids,
            'activeCategory' => DavGroupLabels::ACTIVE_MEMBERS->value,
            'passiveCategory' => DavGroupLabels::PASSIVE_MEMBERS->value,
        ]);
    }

    public function importFromDav(DavSyncService $service, Request $request): RedirectResponse
    {
        $request->validate([
            'confirm' => ['required', 'accepted'],
        ]);

        try {
            $result = $service->importFromDav();
        } catch (\Exception $e) {
            report($e);

            Log::error('DAV import failed', [
                'operation' => 'import',
                'exception' => $e,
            ]);

            return back()->withErrors(['error' => 'Erreur lors de la récupération des contacts.']);
        }

        $message = "$result[created] membre(s) créé(s) avec succès.";

        if ($result['skipped'] !== []) {
            $message .= ' '.count($result['skipped']).' contact(s) ignoré(s) : un membre non lié utilise le même email.';
        }

        return back()->with('success', $message);
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

            return response()->json(['error' => 'Erreur lors de la récupération des contacts.'], 500);
        }

        return response()->json($result);
    }

    public function sync(
        MemberSyncService $service,
        Request $request,
        ?MemberDavSyncFailureNotifier $notifier = null,
    ): RedirectResponse {
        $choices = $request->validate([
            'choices' => 'required|array|min:1',
            'choices.*.member_id' => 'required|exists:members,id',
            'choices.*.fields' => ['present', 'array'],
            'choices.*.fields.*' => 'required|in:member,dav',
        ]);

        try {
            $result = $service->apply($choices['choices']);
        } catch (\Exception $e) {
            report($e);

            Log::error('DAV sync failed', [
                'operation' => 'sync',
                'exception' => $e,
            ]);

            return back()->with('error', 'Erreur lors de la synchronisation.');
        }

        if ($result['failed'] !== []) {
            $notifier ??= app(MemberDavSyncFailureNotifier::class);

            Member::withTrashed()
                ->whereIn('id', $result['failed'])
                ->get()
                ->each(fn (Member $member) => $notifier->notify($member));

            return back()->with('error', sprintf(
                '%d membre(s) synchronisé(s), %d en échec.',
                $result['updated'],
                count($result['failed']),
            ));
        }

        return back()->with('success', "$result[updated] membre(s) synchronisé(s) avec succès.");
    }

    public function link(Request $request, MemberSyncService $service): RedirectResponse
    {
        $data = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'dav_uid' => ['required', 'string'],
        ]);

        $member = Member::withTrashed()->whereKey($data['member_id'])->firstOrFail();
        $result = $service->link($member, $data['dav_uid']);

        if (! $result['success']) {
            return back()->with('error', $result['error']);
        }

        return back()->with('success', 'Contact lié avec succès.');
    }
}
