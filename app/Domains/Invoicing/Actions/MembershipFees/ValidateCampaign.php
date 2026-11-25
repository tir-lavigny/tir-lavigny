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

class ValidateCampaign
{
    public function __construct(
        private readonly MembershipFeeCampaignMemberValidator $validator,
        private readonly MembershipFeeCampaignActivityLogger $activityLogger,
    ) {}

    /**
     * @return array{validated: int, errors: array<int, string>}
     */
    public function execute(MembershipFeeCampaign $campaign, User $actor): array
    {
        return DB::transaction(function () use ($campaign, $actor): array {
            $locked = MembershipFeeCampaign::query()->lockForUpdate()->findOrFail($campaign->id);

            if (! $locked->isEditable()) {
                throw MembershipFeeException::campaignNotEditable();
            }

            $members = $locked->includedMembers()
                ->with('member', 'billingCustomer')
                ->get();

            if ($members->isEmpty()) {
                throw MembershipFeeException::campaignHasNoIncludedMembers();
            }

            $validated = 0;
            $errors = [];

            foreach ($members as $campaignMember) {
                $memberErrors = $this->validator->errors($campaignMember);

                if ($memberErrors !== []) {
                    $errors[] = $this->memberLabel($campaignMember).' : '.implode(' ', $memberErrors);

                    if ($campaignMember->status === CampaignMemberStatus::Validated) {
                        $campaignMember->update([
                            'status' => CampaignMemberStatus::Pending,
                            'validated_by' => null,
                            'validated_at' => null,
                        ]);
                    }

                    continue;
                }

                if ($campaignMember->status !== CampaignMemberStatus::Validated) {
                    $campaignMember->update([
                        'status' => CampaignMemberStatus::Validated,
                        'validated_by' => $actor->id,
                        'validated_at' => now(),
                    ]);

                    $this->activityLogger->record($locked, 'campaign_member.validated', $actor, $campaignMember);
                    $validated++;
                }
            }

            if ($errors === []) {
                if ($locked->status !== MembershipFeeCampaignStatus::Validated) {
                    $locked->update(['status' => MembershipFeeCampaignStatus::Validated]);
                    $this->activityLogger->record($locked, 'campaign.validated', $actor);
                }
            } elseif ($locked->status === MembershipFeeCampaignStatus::Validated) {
                $locked->update(['status' => MembershipFeeCampaignStatus::Preview]);
            }

            return [
                'validated' => $validated,
                'errors' => $errors,
            ];
        });
    }

    private function memberLabel(MembershipFeeCampaignMember $campaignMember): string
    {
        $member = $campaignMember->member;

        if ($member === null) {
            return "Membre #{$campaignMember->member_id}";
        }

        return trim($member->first_name.' '.$member->last_name) ?: "Membre #{$member->id}";
    }
}
