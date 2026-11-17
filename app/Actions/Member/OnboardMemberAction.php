<?php

namespace App\Actions\Member;

use App\Models\Member;
use App\Services\MemberSyncService;
use App\Services\SeasonVoucherService;
use Illuminate\Support\Facades\DB;

class OnboardMemberAction
{
    public function __construct(
        private readonly MemberSyncService $sync,
        private readonly SeasonVoucherService $vouchers,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     member: Member,
     *     success: bool,
     *     error: string|null,
     *     vouchers_created: int,
     *     missing_products: array<int, string>
     * }
     */
    public function execute(
        array $data,
        bool $generateVouchers = true,
        bool $pushDav = true,
    ): array {
        [$member, $voucherResult] = DB::transaction(function () use ($data, $generateVouchers): array {
            $member = Member::create($data);

            $voucherResult = $generateVouchers
                ? $this->vouchers->generateBaseVouchersForMember($member)
                : ['created' => 0, 'missing' => []];

            return [$member, $voucherResult];
        });

        $davResult = $pushDav
            ? $this->sync->push($member)
            : ['success' => true, 'error' => null];

        return [
            'member' => $member,
            'success' => $davResult['success'],
            'error' => $davResult['error'],
            'vouchers_created' => $voucherResult['created'],
            'missing_products' => $voucherResult['missing'],
        ];
    }
}
