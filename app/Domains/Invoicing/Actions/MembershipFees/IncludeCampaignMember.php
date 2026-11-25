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

class IncludeCampaignMember
{
    public function __construct(
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

            if ($locked->status !== CampaignMemberStatus::Excluded) {
                return $locked->refresh();
            }

            $locked->update([
                'status' => CampaignMemberStatus::Pending,
                'validated_by' => null,
                'validated_at' => null,
            ]);

            if ($campaign->status === MembershipFeeCampaignStatus::Validated) {
                $campaign->update(['status' => MembershipFeeCampaignStatus::Preview]);
            }

            $this->activityLogger->record($campaign, 'campaign_member.included', $actor, $locked);

            return $locked->refresh();
        });
    }
}
