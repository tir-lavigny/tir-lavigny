<?php

namespace App\Domains\Invoicing\Actions\MembershipFees;

use App\Domains\Invoicing\Actions\Invoices\CreateInvoice;
use App\Domains\Invoicing\Enums\CampaignMemberStatus;
use App\Domains\Invoicing\Enums\InvoiceItemType;
use App\Domains\Invoicing\Enums\InvoiceType;
use App\Domains\Invoicing\Enums\MembershipFeeCampaignStatus;
use App\Domains\Invoicing\Exceptions\MembershipFeeException;
use App\Domains\Invoicing\Models\MembershipFeeCampaign;
use App\Domains\Invoicing\Models\MembershipFeeCampaignMember;
use App\Domains\Invoicing\Services\MembershipFeeCampaignActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GenerateFeeInvoices
{
    public function __construct(
        private readonly CreateInvoice $createInvoice,
        private readonly MembershipFeeCampaignActivityLogger $activityLogger,
    ) {}

    /**
     * @return array{campaign: MembershipFeeCampaign, generated: int}
     */
    public function execute(MembershipFeeCampaign $campaign, User $actor): array
    {
        return DB::transaction(function () use ($campaign, $actor): array {
            $locked = MembershipFeeCampaign::query()->lockForUpdate()->findOrFail($campaign->id);

            if ($locked->status !== MembershipFeeCampaignStatus::Validated) {
                throw MembershipFeeException::campaignNotValidated();
            }

            if ($locked->hasPendingMembers()) {
                throw MembershipFeeException::campaignHasPendingMembers();
            }

            $members = $locked->includedMembers()
                ->with('billingCustomer')
                ->lockForUpdate()
                ->get();

            if ($members->isEmpty()) {
                throw MembershipFeeException::campaignHasNoIncludedMembers();
            }

            $generated = 0;

            foreach ($members as $campaignMember) {
                if ($campaignMember->invoice_id !== null) {
                    continue;
                }

                if ($campaignMember->status !== CampaignMemberStatus::Validated) {
                    throw MembershipFeeException::campaignHasPendingMembers();
                }

                $invoice = $this->createInvoice->execute([
                    'billing_customer_id' => $campaignMember->billing_customer_id,
                    'membership_fee_campaign_id' => $locked->id,
                    'type' => InvoiceType::MembershipFee,
                    'delivery_method' => $campaignMember->delivery_method->value,
                    'issue_date' => $locked->issue_date->toDateString(),
                    'due_date' => $locked->due_date->toDateString(),
                    'items' => $this->items($locked, $campaignMember),
                ], $actor);

                $campaignMember->update(['invoice_id' => $invoice->id]);
                $generated++;
            }

            if (! $locked->hasUninvoicedMembers()) {
                $locked->update(['status' => MembershipFeeCampaignStatus::Invoiced]);
            }

            $this->activityLogger->record($locked, 'campaign.invoices_generated', $actor, metadata: [
                'generated' => $generated,
            ]);

            return [
                'campaign' => $locked->refresh(),
                'generated' => $generated,
            ];
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function items(MembershipFeeCampaign $campaign, MembershipFeeCampaignMember $campaignMember): array
    {
        $feeTypeName = $campaignMember->fee_type_name ?: 'Cotisation';
        $items = [
            [
                'type' => InvoiceItemType::Fee,
                'description' => "Cotisation {$campaign->year} - {$feeTypeName}",
                'quantity' => '1',
                'unit_price_cents' => $campaignMember->base_amount_cents,
                'metadata' => [
                    'membership_fee_campaign_member_id' => $campaignMember->id,
                    'membership_fee_type_id' => $campaignMember->membership_fee_type_id,
                ],
            ],
        ];

        if ($campaignMember->discount_amount_cents > 0) {
            $label = $campaignMember->hasFullExemption() ? 'Exonération totale' : 'Remise individuelle';
            $description = filled($campaignMember->discount_reason)
                ? "{$label} - {$campaignMember->discount_reason}"
                : $label;

            $items[] = [
                'type' => InvoiceItemType::Discount,
                'description' => $description,
                'quantity' => '1',
                'unit_price_cents' => -$campaignMember->discount_amount_cents,
                'metadata' => [
                    'membership_fee_campaign_member_id' => $campaignMember->id,
                    'discount_reason' => $campaignMember->discount_reason,
                    'full_exemption' => $campaignMember->hasFullExemption(),
                ],
            ];
        }

        return $items;
    }
}
