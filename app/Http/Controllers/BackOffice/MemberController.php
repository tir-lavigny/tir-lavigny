<?php

namespace App\Http\Controllers\BackOffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\ArchiveMemberRequest;
use App\Http\Requests\BackOffice\ForceDeleteMemberRequest;
use App\Http\Requests\BackOffice\StoreMemberRequest;
use App\Http\Requests\BackOffice\UpdateMemberRequest;
use App\MemberStatus;
use App\Models\ActivityLog;
use App\Models\Member;
use App\Models\MemberRegistration;
use App\Models\Season;
use App\Services\MemberSyncService;
use App\Traits\SearchesAccentInsensitively;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller implements HasMiddleware
{
    use SearchesAccentInsensitively;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:manage members'),
        ];
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $archived = $request->boolean('archived');

        $query = Member::query()->withTrashed()->orderByName();

        if ($archived) {
            $query->onlyTrashed();
        } else {
            $query->withoutTrashed();
        }

        if ($status !== null && $status !== '' && in_array($status, MemberStatus::values(), true)) {
            $query->where('status', $status);
        }

        $this->whereAccentInsensitiveLike($query, ['first_name', 'last_name'], $search);

        $members = $query->paginate(15)->withQueryString();

        return Inertia::render('backOffice/Members/Index', [
            'members' => $members,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'archived' => $archived,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('backOffice/Members/Create', [
            'statuses' => MemberStatus::labels(),
        ]);
    }

    public function store(StoreMemberRequest $request, MemberSyncService $sync): RedirectResponse
    {
        $member = Member::create($request->validated());

        $result = $sync->push($member);

        if (! $result['success']) {
            return redirect()->route('bo.members.show', $member)
                ->with('error', 'Membre créé, mais la synchronisation avec les contacts a échoué.');
        }

        return redirect()->route('bo.members.show', $member)
            ->with('success', 'Membre créé avec succès.');
    }

    public function show(Member $member): Response
    {
        if ($member->exists && Auth::user()?->can('view member sensitive data')) {
            $member->makeVisible(['avs']);
        }

        $member->load(['vouchers.ticketItems', 'registration']);
        $seasons = Season::orderBy('id', 'desc')->get();
        $vouchers = $member->vouchers->load('product');
        $usedVouchers = $vouchers->filter(fn ($voucher) => $voucher->isUsed());

        return Inertia::render('backOffice/Members/Show', [
            'member' => $member,
            'seasons' => $seasons,
            'vouchers' => $vouchers,
            'usedVouchers' => $usedVouchers,
        ]);
    }

    public function edit(Member $member): Response
    {
        $member->makeVisible(['email', 'avs']);

        return Inertia::render('backOffice/Members/Edit', [
            'member' => $member,
            'statuses' => MemberStatus::labels(),
        ]);
    }

    public function update(UpdateMemberRequest $request, Member $member, MemberSyncService $sync): RedirectResponse
    {
        $member->update($request->validated());

        $result = $sync->push($member);

        if (! $result['success']) {
            return redirect()->route('bo.members.show', $member)
                ->with('error', 'Membre mis à jour, mais la synchronisation avec les contacts a échoué.');
        }

        return redirect()->route('bo.members.show', $member)
            ->with('success', 'Membre mis à jour avec succès.');
    }

    public function destroy(ArchiveMemberRequest $request, Member $member, MemberSyncService $sync): RedirectResponse
    {
        if ($member->isAnonymized()) {
            return back()->with('error', 'Ce membre a été anonymisé et ne peut plus être archivé.');
        }

        $deleteVouchers = $request->boolean('delete_vouchers');

        if ($deleteVouchers) {
            $member->vouchers()
                ->whereDoesntHave('ticketItems')
                ->delete();
        }

        $member->delete();

        $sync->push($member);

        return redirect()->route('bo.members.index')
            ->with('success', 'Membre archivé avec succès.');
    }

    public function restore(Member $member, MemberSyncService $sync): RedirectResponse
    {
        if ($member->isAnonymized()) {
            return back()->with('error', 'Ce membre a été anonymisé et ne peut pas être restauré.');
        }

        $member->restore();

        $sync->push($member);

        return redirect()->route('bo.members.show', $member)
            ->with('success', 'Membre restauré avec succès.');
    }

    public function forceDelete(ForceDeleteMemberRequest $request, Member $member, MemberSyncService $sync): RedirectResponse
    {
        if ($member->isAnonymized()) {
            return back()->with('error', 'Ce membre a déjà été anonymisé.');
        }

        $hasVouchers = $member->vouchers()->withTrashed()->exists();
        $hasTickets = $member->tickets()->exists();

        if ($hasVouchers || $hasTickets) {
            $this->anonymize($member);

            $sync->delete($member);

            return redirect()->route('bo.members.index')
                ->with('success', 'Membre anonymisé avec succès. Les données personnelles ont été supprimées.');
        }

        $member->registration()->delete();

        $sync->delete($member);

        $member->forceDelete();

        $this->purgeActivityLogs($member);

        return redirect()->route('bo.members.index')
            ->with('success', 'Membre supprimé définitivement.');
    }

    public function sync(Member $member, MemberSyncService $sync): RedirectResponse
    {
        $result = $sync->push($member);

        if (! $result['success']) {
            return redirect()->route('bo.members.show', $member)
                ->with('error', 'La synchronisation avec les contacts a échoué.');
        }

        return redirect()->route('bo.members.show', $member)
            ->with('success', 'Membre synchronisé avec succès.');
    }

    private function anonymize(Member $member): void
    {
        $registrationId = $member->registration?->id;

        $member->registration()->delete();

        $member->forceFill([
            'first_name' => '[Deleted]',
            'last_name' => '',
            'email' => null,
            'tel' => null,
            'avs' => null,
            'avs_hash' => null,
            'is_swiss_citizen' => null,
            'street' => null,
            'npa' => null,
            'city' => null,
            'canton' => null,
            'country' => null,
            'anonymized_at' => now(),
        ])->saveQuietly();

        $member->delete();

        $this->purgeActivityLogs($member, $registrationId);

        Log::info('Member anonymized', [
            'member_id' => $member->id,
        ]);
    }

    private function purgeActivityLogs(Member $member, ?int $registrationId = null): void
    {
        ActivityLog::where('model', Member::class)
            ->where('model_id', $member->id)
            ->delete();

        if ($registrationId !== null) {
            ActivityLog::where('model', MemberRegistration::class)
                ->where('model_id', $registrationId)
                ->delete();
        }

        ActivityLog::create([
            'model' => Member::class,
            'model_id' => $member->id,
            'action' => 'deleted',
            'changes' => json_encode([]),
            'user_id' => Auth::id(),
        ]);
    }
}
