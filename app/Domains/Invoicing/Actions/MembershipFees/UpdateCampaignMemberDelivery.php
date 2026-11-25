<?php

namespace App\Domains\Invoicing\Actions\MembershipFees;

use App\Domains\Invoicing\Enums\CampaignMemberStatus;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Enums\MembershipFeeCampaignStatus;
use App\Domains\Invoicing\Exceptions\MembershipFeeException;
use App\Domains\Invoicing\Models\MembershipFeeCampaign;
use App\Domains\Invoicing\Models\MembershipFeeCampaignMember;
use App\Domains\Invoicing\Services\MembershipFeeCampaignActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateCampaignMemberDelivery
{
    public function __construct(
        private readonly MembershipFeeCampaignActivityLogger $activityLogger,
    ) {}

    public function execute(
        MembershipFeeCampaignMember $campaignMember,
        InvoiceDeliveryMethod $deliveryMethod,
        User $actor,
    ): MembershipFeeCampaignMember {
        return DB::transaction(function () use ($campaignMember, $deliveryMethod, $actor): MembershipFeeCampaignMember {
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

            if ($deliveryMethod === InvoiceDeliveryMethod::Email && blank($locked->billingCustomer?->email)) {
                throw MembershipFeeException::deliveryRequiresEmail();
            }

            $oldDelivery = $locked->delivery_method;

            if ($oldDelivery === $deliveryMethod) {
                return $locked->refresh();
            }

            $locked->update([
                'delivery_method' => $deliveryMethod,
                'status' => CampaignMemberStatus::Pending,
                'validated_by' => null,
                'validated_at' => null,
            ]);

            if ($campaign->status === MembershipFeeCampaignStatus::Validated) {
                $campaign->update(['status' => MembershipFeeCampaignStatus::Preview]);
            }

            $this->activityLogger->record($campaign, 'campaign_member.delivery_changed', $actor, $locked, metadata: [
                'old_delivery_method' => $oldDelivery->value,
                'delivery_method' => $deliveryMethod->value,
            ]);

            return $locked->refresh();
        });
    }
}
