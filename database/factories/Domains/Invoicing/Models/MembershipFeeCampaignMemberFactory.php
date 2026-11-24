<?php

namespace Database\Factories\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\CampaignMemberStatus;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\MembershipFeeCampaign;
use App\Domains\Invoicing\Models\MembershipFeeCampaignMember;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipFeeCampaignMember>
 */
class MembershipFeeCampaignMemberFactory extends Factory
{
    protected $model = MembershipFeeCampaignMember::class;

    public function definition(): array
    {
        $member = Member::factory()->create();
        $billingCustomer = BillingCustomer::factory()->forMember($member)->create();
        $baseAmount = fake()->numberBetween(1000, 30000);

        return [
            'membership_fee_campaign_id' => MembershipFeeCampaign::factory(),
            'member_id' => $member->id,
            'billing_customer_id' => $billingCustomer->id,
            'base_amount_cents' => $baseAmount,
            'discount_amount_cents' => 0,
            'discount_reason' => null,
            'final_amount_cents' => $baseAmount,
            'status' => CampaignMemberStatus::Pending,
            'delivery_method' => InvoiceDeliveryMethod::Email,
            'validated_by' => null,
            'validated_at' => null,
            'invoice_id' => null,
        ];
    }
}
