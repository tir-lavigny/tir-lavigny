<?php

namespace App\Domains\Invoicing\Actions\MembershipFees;

use App\Domains\Invoicing\Enums\MembershipFeeCampaignStatus;
use App\Domains\Invoicing\Models\MembershipFeeCampaign;
use App\Domains\Invoicing\Services\MembershipFeeCampaignActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateFeeCampaign
{
    public function __construct(
        private readonly MembershipFeeCampaignActivityLogger $activityLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, User $actor): MembershipFeeCampaign
    {
        return DB::transaction(function () use ($data, $actor): MembershipFeeCampaign {
            $campaign = MembershipFeeCampaign::create([
                'year' => (int) $data['year'],
                'name' => (string) $data['name'],
                'issue_date' => (string) $data['issue_date'],
                'due_date' => (string) $data['due_date'],
                'status' => MembershipFeeCampaignStatus::Draft,
                'created_by' => $actor->id,
            ]);

            $this->activityLogger->record($campaign, 'campaign.created', $actor, metadata: [
                'year' => $campaign->year,
                'name' => $campaign->name,
            ]);

            return $campaign;
        });
    }
}
