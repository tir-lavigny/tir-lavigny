<?php

namespace App\Actions\Member;

use App\Models\Member;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;

class DeleteUnusedMemberVouchersAction
{
    public function execute(Member $member): int
    {
        return DB::transaction(function () use ($member): int {
            $vouchers = $member->vouchers()
                ->whereDoesntHave('ticketItems', fn ($query) => $query->withTrashed())
                ->get();

            $vouchers->each(fn (Voucher $voucher) => $voucher->delete());

            return $vouchers->count();
        });
    }
}
