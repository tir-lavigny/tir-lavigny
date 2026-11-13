<?php

namespace App\Http\Controllers\BackOffice;

use App\Actions\Member\ArchiveMemberAction;
use App\Actions\Member\CreateMemberAction;
use App\Actions\Member\DeleteMemberAction;
use App\Actions\Member\RestoreMemberAction;
use App\Actions\Member\SyncMemberAction;
use App\Actions\Member\UpdateMemberAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\ArchiveMemberRequest;
use App\Http\Requests\BackOffice\ForceDeleteMemberRequest;
use App\Http\Requests\BackOffice\StoreMemberRequest;
use App\Http\Requests\BackOffice\UpdateMemberRequest;
use App\MemberStatus;
use App\Models\Member;
use App\Models\Season;
use App\Services\MemberDavSyncFailureNotifier;
use App\Traits\SearchesAccentInsensitively;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
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

        $this->whereAccentInsensitiveLike($query, ['first_name', 'last_name', 'email'], $search);

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

    public function store(
        StoreMemberRequest $request,
        CreateMemberAction $action,
        MemberDavSyncFailureNotifier $notifier,
    ): RedirectResponse {
        $result = $action->execute($this->filterSensitiveFields($request->validated()));

        if (! $result['success']) {
            $notifier->notify($result['member']);

            return redirect()->route('bo.members.show', $result['member'])
                ->with('error', 'Membre créé, mais la synchronisation avec les contacts a échoué.');
        }

        return redirect()->route('bo.members.show', $result['member'])
            ->with('success', 'Membre créé avec succès.');
    }

    public function show(Member $member): Response
    {
        if ($this->canViewSensitiveData()) {
            $member->makeVisible(['email', 'avs']);
        }

        $member->load(['vouchers.ticketItems', 'registration', 'tickets']);
        $seasons = Season::orderBy('id', 'desc')->get();
        $vouchers = $member->vouchers->load('product');
        $usedVouchers = $vouchers->filter(fn ($voucher) => $voucher->isUsed())->values();

        return Inertia::render('backOffice/Members/Show', [
            'member' => $member,
            'seasons' => $seasons,
            'vouchers' => $vouchers,
            'usedVouchers' => $usedVouchers,
        ]);
    }

    public function edit(Member $member): Response
    {
        if ($member->isAnonymized()) {
            abort(403, 'Ce membre a été anonymisé et ne peut plus être modifié.');
        }

        if ($this->canViewSensitiveData()) {
            $member->makeVisible(['email', 'avs']);
        }

        return Inertia::render('backOffice/Members/Edit', [
            'member' => $member,
            'statuses' => MemberStatus::labels(),
        ]);
    }

    public function update(
        UpdateMemberRequest $request,
        Member $member,
        UpdateMemberAction $action,
        MemberDavSyncFailureNotifier $notifier,
    ): RedirectResponse {
        if ($member->isAnonymized()) {
            return back()->with('error', 'Ce membre a été anonymisé et ne peut plus être modifié.');
        }

        $result = $action->execute($member, $this->filterSensitiveFields($request->validated()));

        if (! $result['success']) {
            $notifier->notify($result['member']);

            return redirect()->route('bo.members.show', $result['member'])
                ->with('error', 'Membre mis à jour, mais la synchronisation avec les contacts a échoué.');
        }

        return redirect()->route('bo.members.show', $result['member'])
            ->with('success', 'Membre mis à jour avec succès.');
    }

    public function destroy(
        ArchiveMemberRequest $request,
        Member $member,
        ArchiveMemberAction $action,
        MemberDavSyncFailureNotifier $notifier,
    ): RedirectResponse {
        if ($member->isAnonymized()) {
            return back()->with('error', 'Ce membre a été anonymisé et ne peut plus être archivé.');
        }

        $result = $action->execute($member, $request->boolean('delete_vouchers'));

        if (! $result['success']) {
            $notifier->notify($member);

            return redirect()->route('bo.members.index')
                ->with('error', 'Membre archivé, mais la synchronisation avec les contacts a échoué.');
        }

        return redirect()->route('bo.members.index')
            ->with('success', 'Membre archivé avec succès.');
    }

    public function restore(
        Member $member,
        RestoreMemberAction $action,
        MemberDavSyncFailureNotifier $notifier,
    ): RedirectResponse {
        if ($member->isAnonymized()) {
            return back()->with('error', 'Ce membre a été anonymisé et ne peut pas être restauré.');
        }

        $result = $action->execute($member);

        if (! $result['success']) {
            $notifier->notify($result['member']);

            return redirect()->route('bo.members.show', $result['member'])
                ->with('error', 'Membre restauré, mais la synchronisation avec les contacts a échoué.');
        }

        return redirect()->route('bo.members.show', $result['member'])
            ->with('success', 'Membre restauré avec succès.');
    }

    public function forceDelete(
        ForceDeleteMemberRequest $request,
        Member $member,
        DeleteMemberAction $action,
        MemberDavSyncFailureNotifier $notifier,
    ): RedirectResponse {
        if ($member->isAnonymized()) {
            return back()->with('error', 'Ce membre a déjà été anonymisé.');
        }

        $result = $action->execute($member);

        if (! $result['dav_success']) {
            $notifier->notify($member);

            return redirect()->route('bo.members.index')
                ->with('error', $result['anonymized']
                    ? 'Membre anonymisé, mais la suppression du contact a échoué.'
                    : 'Membre supprimé, mais la suppression du contact a échoué.');
        }

        return redirect()->route('bo.members.index')
            ->with('success', $result['anonymized']
                ? 'Membre anonymisé avec succès. Les données personnelles ont été supprimées.'
                : 'Membre supprimé définitivement.');
    }

    public function sync(
        Member $member,
        SyncMemberAction $action,
        MemberDavSyncFailureNotifier $notifier,
    ): RedirectResponse {
        if ($member->isAnonymized()) {
            return back()->with('error', 'Ce membre a été anonymisé et ne peut plus être synchronisé.');
        }

        $result = $action->execute($member);

        if (! $result['success']) {
            $notifier->notify($member);

            return redirect()->route('bo.members.show', $member)
                ->with('error', 'La synchronisation avec les contacts a échoué.');
        }

        return redirect()->route('bo.members.show', $member)
            ->with('success', 'Membre synchronisé avec succès.');
    }

    private function canViewSensitiveData(): bool
    {
        return Auth::user()?->can('view member sensitive data') ?? false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function filterSensitiveFields(array $data): array
    {
        if ($this->canViewSensitiveData()) {
            return $data;
        }

        unset($data['email'], $data['avs']);

        return $data;
    }
}
