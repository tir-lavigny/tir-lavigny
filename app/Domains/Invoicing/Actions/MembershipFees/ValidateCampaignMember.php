<?php

namespace App\Domains\Invoicing\Actions\MembershipFees;

use App\Domains\Invoicing\Enums\CampaignMemberStatus;
use App\Domains\Invoicing\Enums\MembershipFeeCampaignStatus;
use App\Domains\Invoicing\Exceptions\MembershipFeeException;
use App\Domains\Invoicing\Models\MembershipFeeCampaign;
use App\Domains\Invoicing\Models\MembershipFeeCampaignMember;
use App\Domains\Invoicing\Services\MembershipFeeCampaignActivityLogger;
use App\Domains\Invoicing\Services\MembershipFeeCampaignMemberValidator;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ValidateCampaignMember
{
    public function __construct(
        private readonly MembershipFeeCampaignMemberValidator $validator,
        private readonly MembershipFeeCampaignActivityLogger $activityLogger,
    ) {}

    public function execute(MembershipFeeCampaignMember $campaignMember, User $actor): MembershipFeeCampaignMember
    {
        return DB::transaction(function () use ($campaignMember, $actor): MembershipFeeCampaignMember {
            $locked = MembershipFeeCampaignMember::query()->lockForUpdate()->findOrFail($campaignMember->id);
            $campaign = MembershipFeeCampaign::query()->lockForUpdate()->findOrFail($locked->membership_fee_campaign_id);

            if (! $campaign->isEditable() || $locked->invoice_id !== null) {
                throw MembershipFeeException::campaignMemberNotEditable();
            }

            if ($locked->status === CampaignMemberStatus::Excluded) {
                throw MembershipFeeException::campaignMemberNotEditable();
            }

            $errors = $this->validator->errors($locked);

            if ($errors !== []) {
                throw MembershipFeeException::memberCannotBeInvoiced(implode(' ', $errors));
            }

            if ($locked->status !== CampaignMemberStatus::Validated) {
                $locked->update([
                    'status' => CampaignMemberStatus::Validated,
                    'validated_by' => $actor->id,
                    'validated_at' => now(),
                ]);

                $this->activityLogger->record($campaign, 'campaign_member.validated', $actor, $locked);
            }

            if ($campaign->status === MembershipFeeCampaignStatus::Draft) {
                $campaign->update(['status' => MembershipFeeCampaignStatus::Preview]);
            }

            $this->validateCampaignWhenReady($campaign, $actor);

            return $locked->refresh();
        });
    }

    private function validateCampaignWhenReady(MembershipFeeCampaign $campaign, User $actor): void
    {
        if ($campaign->status === MembershipFeeCampaignStatus::Validated) {
            return;
        }

        if ($campaign->hasPendingMembers() || ! $campaign->includedMembers()->exists()) {
            return;
        }

        $campaign->update(['status' => MembershipFeeCampaignStatus::Validated]);
        $this->activityLogger->record($campaign, 'campaign.validated', $actor);
    }
}
