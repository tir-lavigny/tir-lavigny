<?php

namespace App\Domains\Invoicing\Actions\MembershipFees;

use App\Domains\Invoicing\Enums\CampaignMemberStatus;
use App\Domains\Invoicing\Enums\MembershipFeeCampaignStatus;
use App\Domains\Invoicing\Exceptions\MembershipFeeException;
use App\Domains\Invoicing\Models\MembershipFeeCampaign;
use App\Domains\Invoicing\Models\MembershipFeeCampaignMember;
use App\Domains\Invoicing\Services\MembershipFeeCampaignActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateCampaignMemberDiscount
{
    public function __construct(
        private readonly MembershipFeeCampaignActivityLogger $activityLogger,
    ) {}

    public function execute(
        MembershipFeeCampaignMember $campaignMember,
        int $discountCents,
        ?string $reason,
        User $actor,
    ): MembershipFeeCampaignMember {
        return DB::transaction(function () use ($campaignMember, $discountCents, $reason, $actor): MembershipFeeCampaignMember {
            $locked = MembershipFeeCampaignMember::query()->lockForUpdate()->findOrFail($campaignMember->id);
            $campaign = MembershipFeeCampaign::query()->lockForUpdate()->findOrFail($locked->membership_fee_campaign_id);

            if (! $campaign->isEditable()) {
                throw MembershipFeeException::campaignNotEditable();
            }

            if ($locked->invoice_id !== null) {
                throw MembershipFeeException::campaignMemberAlreadyInvoiced();
            }

            if ($locked->status === CampaignMemberStatus::Excluded) {
                throw MembershipFeeException::campaignMemberNotEditable();
            }

            if ($discountCents < 0) {
                throw MembershipFeeException::discountCannotBeNegative();
            }

            if ($discountCents > $locked->base_amount_cents) {
                throw MembershipFeeException::discountExceedsBase();
            }

            $reason = filled($reason) ? trim((string) $reason) : null;
            $isFullExemption = $locked->base_amount_cents > 0 && $discountCents === $locked->base_amount_cents;

            if ($isFullExemption && blank($reason)) {
                throw MembershipFeeException::discountReasonRequired();
            }

            $oldDiscount = $locked->discount_amount_cents;
            $oldReason = $locked->discount_reason;

            if ($oldDiscount === $discountCents && $oldReason === $reason) {
                return $locked->refresh();
            }

            $locked->update([
                'discount_amount_cents' => $discountCents,
                'discount_reason' => $reason,
                'final_amount_cents' => $locked->base_amount_cents - $discountCents,
                'status' => CampaignMemberStatus::Pending,
                'validated_by' => null,
                'validated_at' => null,
            ]);

            if ($campaign->status === MembershipFeeCampaignStatus::Validated) {
                $campaign->update(['status' => MembershipFeeCampaignStatus::Preview]);
            }

            $this->activityLogger->record(
                $campaign,
                $isFullExemption ? 'campaign_member.exempted' : 'campaign_member.discount_updated',
                $actor,
                $locked,
                metadata: [
                    'old_discount_cents' => $oldDiscount,
                    'discount_cents' => $discountCents,
                    'old_reason' => $oldReason,
                    'reason' => $reason,
                ],
            );

            return $locked->refresh();
        });
    }
}
