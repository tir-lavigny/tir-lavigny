<?php

namespace App\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\Register\UpdateMemberRequest;
use App\Models\Member;
use App\Services\InfomaniakDavService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:manage members', only: ['create', 'edit', 'update', 'destroy']),
        ];
    }

    public function index(): Response
    {
        $members = Member::orderBy('last_name')->paginate(15);

        return Inertia::render('backOffice/Register/Members/Index', [
            'members' => $members,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('backOffice/Register/Members/Create');
    }

    public function edit(Member $member): Response
    {
        return Inertia::render('backOffice/Register/Members/Edit', [
            'member' => $member,
        ]);
    }

    public function update(UpdateMemberRequest $request, Member $member)
    {
        $member->update($request->validated());

        return redirect()->route('bo.register.members.index')->with('success', 'Member updated successfully.');
    }

    public function destroy(Member $member)
    {
        $davRes = (new InfomaniakDavService)->deleteMember($member);

        if (! $davRes) {
            return redirect()->route('bo.register.members.index')->with('error', 'Failed to delete member from Infomaniak DAV.');
        }

        $member->delete();

        return redirect()->route('bo.register.members.index')->with('success', 'Member deleted successfully.');
    }
}
