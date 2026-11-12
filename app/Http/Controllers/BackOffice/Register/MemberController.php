<?php

namespace App\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\Register\UpdateMemberRequest;
use App\Models\Member;
use App\Models\Season;
use App\Services\DavServiceInterface;
use App\Traits\SearchesAccentInsensitively;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller implements HasMiddleware
{
    use SearchesAccentInsensitively;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:manage members', only: ['edit', 'update', 'destroy']),
        ];
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search');

        $query = Member::query()->orderByName();
        $this->whereAccentInsensitiveLike($query, ['first_name', 'last_name'], $search);

        $members = $query->paginate(15)->withQueryString();

        return Inertia::render('backOffice/Register/Members/Index', [
            'members' => $members,
            'filters' => ['search' => $search],
        ]);
    }

    public function show(Member $member): Response
    {
        $member->load('vouchers.ticketItems');
        $seasons = Season::orderBy('id', 'desc')->get();
        $vouchers = $member->vouchers->load('product');
        $usedVouchers = $vouchers->filter(fn ($v) => $v->isUsed());

        return Inertia::render('backOffice/Register/Members/Show', [
            'member' => $member,
            'seasons' => $seasons,
            'vouchers' => $vouchers,
            'usedVouchers' => $usedVouchers,
        ]);
    }

    public function edit(Member $member): Response
    {
        return Inertia::render('backOffice/Register/Members/Edit', [
            'member' => $member,
        ]);
    }

    public function update(UpdateMemberRequest $request, Member $member, DavServiceInterface $dav): RedirectResponse
    {
        $member->update($request->validated());

        $davRes = $dav->updateMember($member);

        if (! $davRes) {
            return redirect()->route('bo.register.members.index')->with('error', 'Impossible de mettre à jour le membre dans les contacts.');
        }

        return redirect()->route('bo.register.members.index')->with('success', 'Membre mis à jour avec succès.');
    }

    public function destroy(Member $member, DavServiceInterface $dav): RedirectResponse
    {
        $davRes = $dav->softDeleteMember($member);

        if (! $davRes) {
            return back()->withErrors(['error' => 'Impossible de supprimer le membre dans les contacts.']);
        }

        $member->delete();

        return back();
    }
}
