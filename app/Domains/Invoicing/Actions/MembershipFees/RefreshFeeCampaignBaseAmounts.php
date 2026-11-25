<?php

namespace App\Domains\Invoicing\Actions\MembershipFees;

use App\Domains\Invoicing\Enums\CampaignMemberStatus;
use App\Domains\Invoicing\Enums\MembershipFeeCampaignStatus;
use App\Domains\Invoicing\Exceptions\MembershipFeeException;
use App\Domains\Invoicing\Models\MembershipFeeCampaign;
use App\Domains\Invoicing\Models\MembershipFeeCampaignMember;
use App\Domains\Invoicing\Services\MembershipFeeCampaignActivityLogger;
use App\Domains\Invoicing\Services\MembershipFeeResolver;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RefreshFeeCampaignBaseAmounts
{
    public function __construct(
        private readonly MembershipFeeResolver $resolver,
        private readonly MembershipFeeCampaignActivityLogger $activityLogger,
    ) {}

    /**
     * @return array{campaign: MembershipFeeCampaign, updated: int}
     */
    public function execute(MembershipFeeCampaign $campaign, User $actor): array
    {
        return DB::transaction(function () use ($campaign, $actor): array {
            $locked = MembershipFeeCampaign::query()->lockForUpdate()->findOrFail($campaign->id);

            if (! $locked->isEditable()) {
                throw MembershipFeeException::campaignNotEditable();
            }

            if ($locked->invoices()->exists()) {
                throw MembershipFeeException::campaignHasInvoices();
            }

            $members = $locked->members()
                ->with('member')
                ->lockForUpdate()
                ->get();

            if ($members->isEmpty()) {
                throw MembershipFeeException::campaignHasNoMembers();
            }

            $changes = [];
            $conflicts = [];

            foreach ($members as $campaignMember) {
                $member = $campaignMember->member;

                if ($member === null) {
                    throw MembershipFeeException::memberCannotBeInvoiced('le membre est introuvable.');
                }

                $feeType = $this->resolver->resolve($member);
                $newDiscountCents = $campaignMember->hasFullExemption()
                    ? $feeType->amount_cents
                    : $campaignMember->discount_amount_cents;

                if ($newDiscountCents > $feeType->amount_cents) {
                    $conflicts[] = $this->memberLabel($campaignMember);

                    continue;
                }

                $changes[] = [
                    'campaign_member' => $campaignMember,
                    'fee_type_id' => $feeType->id,
                    'fee_type_name' => $feeType->name,
                    'base_amount_cents' => $feeType->amount_cents,
                    'discount_amount_cents' => $newDiscountCents,
                    'final_amount_cents' => $feeType->amount_cents - $newDiscountCents,
                ];
            }

            if ($conflicts !== []) {
                throw MembershipFeeException::baseAmountRefreshConflicts($conflicts);
            }

            $updated = 0;

            foreach ($changes as $change) {
                /** @var MembershipFeeCampaignMember $campaignMember */
                $campaignMember = $change['campaign_member'];
                $old = [
                    'membership_fee_type_id' => $campaignMember->membership_fee_type_id,
                    'fee_type_name' => $campaignMember->fee_type_name,
                    'base_amount_cents' => $campaignMember->base_amount_cents,
                    'discount_amount_cents' => $campaignMember->discount_amount_cents,
                ];

                if (
                    $old['membership_fee_type_id'] === $change['fee_type_id']
                    && $old['fee_type_name'] === $change['fee_type_name']
                    && $old['base_amount_cents'] === $change['base_amount_cents']
                    && $old['discount_amount_cents'] === $change['discount_amount_cents']
                ) {
                    continue;
                }

                $campaignMember->update([
                    'membership_fee_type_id' => $change['fee_type_id'],
                    'fee_type_name' => $change['fee_type_name'],
                    'base_amount_cents' => $change['base_amount_cents'],
                    'discount_amount_cents' => $change['discount_amount_cents'],
                    'final_amount_cents' => $change['final_amount_cents'],
                    'status' => $campaignMember->status === CampaignMemberStatus::Excluded
                        ? CampaignMemberStatus::Excluded
                        : CampaignMemberStatus::Pending,
                    'validated_by' => null,
                    'validated_at' => null,
                ]);

                $this->activityLogger->record($locked, 'campaign_member.base_amount_updated', $actor, $campaignMember, metadata: [
                    'old' => $old,
                    'new' => [
                        'membership_fee_type_id' => $change['fee_type_id'],
                        'fee_type_name' => $change['fee_type_name'],
                        'base_amount_cents' => $change['base_amount_cents'],
                        'discount_amount_cents' => $change['discount_amount_cents'],
                    ],
                ]);

                $updated++;
            }

            if ($updated > 0 && in_array($locked->status, [
                MembershipFeeCampaignStatus::Draft,
                MembershipFeeCampaignStatus::Validated,
            ], true)) {
                $locked->update(['status' => MembershipFeeCampaignStatus::Preview]);
            }

            $this->activityLogger->record($locked, 'campaign.base_amounts_refreshed', $actor, metadata: [
                'updated' => $updated,
            ]);

            return [
                'campaign' => $locked->refresh(),
                'updated' => $updated,
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
