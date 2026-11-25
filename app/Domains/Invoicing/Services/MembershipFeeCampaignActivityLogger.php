<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Models\MembershipFeeCampaign;
use App\Domains\Invoicing\Models\MembershipFeeCampaignActivityLog;
use App\Domains\Invoicing\Models\MembershipFeeCampaignMember;
use App\Models\User;

class MembershipFeeCampaignActivityLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        MembershipFeeCampaign $campaign,
        string $action,
        ?User $actor,
        ?MembershipFeeCampaignMember $campaignMember = null,
        ?string $description = null,
        array $metadata = [],
    ): MembershipFeeCampaignActivityLog {
        return MembershipFeeCampaignActivityLog::create([
            'membership_fee_campaign_id' => $campaign->id,
            'membership_fee_campaign_member_id' => $campaignMember?->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
