<?php

namespace App\Actions\Member;

use App\Models\ActivityLog;
use App\Models\Member;
use App\Models\MemberRegistration;
use App\Services\MemberSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DeleteMemberAction
{
    public function __construct(
        private readonly MemberSyncService $sync,
    ) {}

    /** @return array{anonymized: bool, dav_success: bool} */
    public function execute(Member $member): array
    {
        $hasVouchers = $member->vouchers()->withTrashed()->exists();
        $hasTickets = $member->tickets()->withTrashed()->exists();
        $anonymized = $hasVouchers || $hasTickets;
        $registrationId = $member->registration?->id;

        DB::transaction(function () use ($member, $anonymized, $registrationId): void {
            if ($registrationId !== null) {
                MemberRegistration::whereKey($registrationId)->delete();
            }

            if ($anonymized) {
                $this->anonymize($member);
            } else {
                $member->forceDelete();
            }

            $this->purgeActivityLogs($member, $registrationId);
        });

        $syncResult = $this->sync->delete($member);

        return [
            'anonymized' => $anonymized,
            'dav_success' => $syncResult['success'],
        ];
    }

    private function anonymize(Member $member): void
    {
        $member->forceFill([
            'first_name' => '[Deleted]',
            'last_name' => '',
            'email' => null,
            'tel' => null,
            'avs' => null,
            'avs_hash' => null,
            'is_swiss_citizen' => null,
            'street' => null,
            'npa' => null,
            'city' => null,
            'canton' => null,
            'country' => null,
            'dav_synced_at' => null,
            'dav_synced_snapshot' => null,
            'dav_sync_error' => null,
            'anonymized_at' => now(),
        ])->saveQuietly();

        $member->delete();
    }

    private function purgeActivityLogs(Member $member, ?int $registrationId): void
    {
        ActivityLog::where('model', Member::class)
            ->where('model_id', $member->id)
            ->delete();

        if ($registrationId !== null) {
            ActivityLog::where('model', MemberRegistration::class)
                ->where('model_id', $registrationId)
                ->delete();
        }

        ActivityLog::create([
            'model' => Member::class,
            'model_id' => $member->id,
            'action' => 'deleted',
            'changes' => json_encode([]),
            'user_id' => Auth::id(),
        ]);
    }
}
