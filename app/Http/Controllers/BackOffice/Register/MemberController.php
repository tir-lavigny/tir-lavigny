<?php

namespace App\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\Register\UpdateMemberRequest;
use App\Models\Member;
use App\Services\DavServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:manage members', only: ['edit', 'update', 'destroy']),
        ];
    }

    public function index(): Response
    {
        $members = Member::orderBy('last_name')->paginate(15);

        return Inertia::render('backOffice/Register/Members/Index', [
            'members' => $members,
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
