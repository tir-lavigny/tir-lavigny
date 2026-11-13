<?php

namespace App\Actions\Member;

use App\Models\Member;
use App\Services\MemberSyncService;
use Illuminate\Support\Facades\DB;

class UpdateMemberAction
{
    public function __construct(
        private readonly MemberSyncService $sync,
    ) {}

    /** @return array{member: Member, success: bool, error: string|null} */
    public function execute(Member $member, array $data): array
    {
        DB::transaction(fn () => $member->update($data));

        $result = $this->sync->push($member);

        return [
            'member' => $member,
            'success' => $result['success'],
            'error' => $result['error'],
        ];
    }
}
