<?php

namespace App\Actions\Member;

use App\Models\Member;
use App\Services\MemberSyncService;
use Illuminate\Support\Facades\DB;

class RestoreMemberAction
{
    public function __construct(
        private readonly MemberSyncService $sync,
    ) {}

    /** @return array{member: Member, success: bool, error: string|null} */
    public function execute(Member $member): array
    {
        DB::transaction(fn () => $member->restore());

        $result = $this->sync->push($member);

        return [
            'member' => $member,
            'success' => $result['success'],
            'error' => $result['error'],
        ];
    }
}
