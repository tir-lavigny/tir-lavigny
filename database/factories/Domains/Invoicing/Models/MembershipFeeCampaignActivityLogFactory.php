<?php

namespace Database\Factories\Domains\Invoicing\Models;

use App\Domains\Invoicing\Models\MembershipFeeCampaign;
use App\Domains\Invoicing\Models\MembershipFeeCampaignActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipFeeCampaignActivityLog>
 */
class MembershipFeeCampaignActivityLogFactory extends Factory
{
    protected $model = MembershipFeeCampaignActivityLog::class;

    public function definition(): array
    {
        return [
            'membership_fee_campaign_id' => MembershipFeeCampaign::factory(),
            'membership_fee_campaign_member_id' => null,
            'user_id' => User::factory(),
            'action' => 'campaign.created',
            'description' => null,
            'metadata' => null,
        ];
    }
}
