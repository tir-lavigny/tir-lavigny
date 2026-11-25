<?php

namespace App\Domains\Invoicing\Actions\MembershipFees;

use App\Domains\Invoicing\Exceptions\MembershipFeeException;
use App\Domains\Invoicing\Models\MembershipFeeCampaign;
use App\Domains\Invoicing\Services\MembershipFeeCampaignActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateFeeCampaign
{
    public function __construct(
        private readonly MembershipFeeCampaignActivityLogger $activityLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(MembershipFeeCampaign $campaign, array $data, User $actor): MembershipFeeCampaign
    {
        return DB::transaction(function () use ($campaign, $data, $actor): MembershipFeeCampaign {
            $locked = MembershipFeeCampaign::query()->lockForUpdate()->findOrFail($campaign->id);

            if (! $locked->isEditable() || $locked->invoices()->exists()) {
                throw MembershipFeeException::campaignNotEditable();
            }

            $locked->update([
                'year' => (int) $data['year'],
                'name' => (string) $data['name'],
                'issue_date' => (string) $data['issue_date'],
                'due_date' => (string) $data['due_date'],
            ]);

            $this->activityLogger->record($locked, 'campaign.updated', $actor, metadata: [
                'year' => $locked->year,
                'name' => $locked->name,
                'issue_date' => $locked->issue_date->toDateString(),
                'due_date' => $locked->due_date->toDateString(),
            ]);

            return $locked->refresh();
        });
    }
}
