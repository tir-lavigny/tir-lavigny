<?php

namespace App\Domains\Invoicing\Actions\MembershipFees;

use App\Domains\Invoicing\Actions\Customers\SyncMemberBillingCustomer;
use App\Domains\Invoicing\Enums\CampaignMemberStatus;
use App\Domains\Invoicing\Enums\MembershipFeeCampaignStatus;
use App\Domains\Invoicing\Exceptions\MembershipFeeException;
use App\Domains\Invoicing\Models\MembershipFeeCampaign;
use App\Domains\Invoicing\Models\MembershipFeeCampaignMember;
use App\Domains\Invoicing\Services\MembershipFeeCampaignActivityLogger;
use App\Domains\Invoicing\Services\MembershipFeeResolver;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PreviewFeeCampaign
{
    public function __construct(
        private readonly MembershipFeeResolver $resolver,
        private readonly SyncMemberBillingCustomer $syncBillingCustomer,
        private readonly MembershipFeeCampaignActivityLogger $activityLogger,
    ) {}

    public function execute(MembershipFeeCampaign $campaign, User $actor): MembershipFeeCampaign
    {
        return DB::transaction(function () use ($campaign, $actor): MembershipFeeCampaign {
            $locked = MembershipFeeCampaign::query()->lockForUpdate()->findOrFail($campaign->id);

            if (! $locked->isEditable()) {
                throw MembershipFeeException::campaignNotEditable();
            }

            $members = Member::query()
                ->with('billingCustomer')
                ->whereNull('anonymized_at')
                ->orderByName()
                ->get();

            if ($members->isEmpty()) {
                throw MembershipFeeException::campaignHasNoMembers();
            }

            $this->resolver->assertStatusesConfigured(
                $members->pluck('status')->all(),
            );

            $existing = MembershipFeeCampaignMember::query()
                ->where('membership_fee_campaign_id', $locked->id)
                ->get()
                ->keyBy('member_id');

            $added = 0;

            foreach ($members as $member) {
                if ($existing->has($member->id)) {
                    continue;
                }

                $feeType = $this->resolver->resolve($member);
                $billingCustomer = $member->billingCustomer ?? $this->syncBillingCustomer->execute($member);

                MembershipFeeCampaignMember::create([
                    'membership_fee_campaign_id' => $locked->id,
                    'member_id' => $member->id,
                    'billing_customer_id' => $billingCustomer->id,
                    'membership_fee_type_id' => $feeType->id,
                    'fee_type_name' => $feeType->name,
                    'base_amount_cents' => $feeType->amount_cents,
                    'discount_amount_cents' => 0,
                    'discount_reason' => null,
                    'final_amount_cents' => $feeType->amount_cents,
                    'status' => CampaignMemberStatus::Pending,
                    'delivery_method' => $billingCustomer->preferred_invoice_delivery,
                ]);

                $added++;
            }

            if ($locked->status === MembershipFeeCampaignStatus::Draft) {
                $locked->update(['status' => MembershipFeeCampaignStatus::Preview]);
            } elseif ($locked->status === MembershipFeeCampaignStatus::Validated && $added > 0) {
                $locked->update(['status' => MembershipFeeCampaignStatus::Preview]);
            }

            $total = MembershipFeeCampaignMember::query()
                ->where('membership_fee_campaign_id', $locked->id)
                ->count();

            $this->activityLogger->record($locked, 'campaign.preview_generated', $actor, metadata: [
                'added' => $added,
                'total' => $total,
            ]);

            return $locked->refresh();
        });
    }
}
