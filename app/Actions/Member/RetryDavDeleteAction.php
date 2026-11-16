<?php

namespace App\Actions\Member;

use App\Models\Member;
use App\Services\MemberSyncService;

class RetryDavDeleteAction
{
    public function __construct(
        private readonly MemberSyncService $sync,
    ) {}

    /** @return array{success: bool, error: string|null} */
    public function execute(Member $member): array
    {
        if (! $member->isAnonymized()) {
            return ['success' => false, 'error' => 'Seul un membre anonymisé peut relancer la suppression du contact.'];
        }

        return $this->sync->delete($member);
    }
}
