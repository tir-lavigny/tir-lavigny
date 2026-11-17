<?php

namespace App\Actions\Member;

use App\Models\Member;
use App\Services\MemberSyncService;

class SyncMemberAction
{
    public function __construct(
        private readonly MemberSyncService $sync,
    ) {}

    /** @return array{success: bool, error: string|null} */
    public function execute(Member $member): array
    {
        return $this->sync->push($member);
    }
}
