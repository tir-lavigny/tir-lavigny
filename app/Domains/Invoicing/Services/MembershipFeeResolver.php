<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Exceptions\MembershipFeeException;
use App\Domains\Invoicing\Models\MembershipFeeType;
use App\MemberStatus;
use App\Models\Member;

class MembershipFeeResolver
{
    public function resolve(Member $member): MembershipFeeType
    {
        $feeType = MembershipFeeType::query()
            ->where('is_active', true)
            ->where('member_status', $member->status)
            ->first();

        if ($feeType === null) {
            throw MembershipFeeException::feeTypeNotConfigured($member->status);
        }

        return $feeType;
    }

    /**
     * @param  array<int, MemberStatus>  $statuses
     */
    public function assertStatusesConfigured(array $statuses): void
    {
        foreach (array_unique(array_map(fn (MemberStatus $status): string => $status->value, $statuses)) as $status) {
            $memberStatus = MemberStatus::from($status);

            $exists = MembershipFeeType::query()
                ->where('is_active', true)
                ->where('member_status', $memberStatus)
                ->exists();

            if (! $exists) {
                throw MembershipFeeException::feeTypeNotConfigured($memberStatus);
            }
        }
    }
}
