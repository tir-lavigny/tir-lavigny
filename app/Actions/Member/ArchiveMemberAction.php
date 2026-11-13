<?php

namespace App\Actions\Member;

use App\Models\Member;
use App\Services\MemberSyncService;
use Illuminate\Support\Facades\DB;

class ArchiveMemberAction
{
    public function __construct(
        private readonly MemberSyncService $sync,
    ) {}

    /** @return array{success: bool, error: string|null} */
    public function execute(Member $member, bool $deleteUnusedVouchers): array
    {
        DB::transaction(function () use ($member, $deleteUnusedVouchers): void {
            if ($deleteUnusedVouchers) {
                $member->vouchers()
                    ->whereDoesntHave('ticketItems')
                    ->delete();
            }

            $member->delete();
        });

        return $this->sync->push($member);
    }
}
