<?php

namespace App\Domains\Invoicing\Actions\Invoices;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\MembershipFeeCampaignStatus;
use App\Domains\Invoicing\Exceptions\InvoiceNotDraft;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\MembershipFeeCampaign;
use App\Domains\Invoicing\Services\InvoiceActivityLogger;
use App\Domains\Invoicing\Services\MembershipFeeCampaignActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteDraftInvoice
{
    public function __construct(
        private readonly InvoiceActivityLogger $activityLogger,
        private readonly MembershipFeeCampaignActivityLogger $campaignLogger,
    ) {}

    public function execute(Invoice $invoice, User $actor): void
    {
        DB::transaction(function () use ($invoice, $actor): void {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($locked->status !== InvoiceStatus::Draft) {
                throw InvoiceNotDraft::for($locked);
            }

            $campaignId = $locked->membership_fee_campaign_id;
            $this->activityLogger->record($locked, 'invoice.deleted', $actor);
            $locked->delete();

            if ($campaignId === null) {
                return;
            }

            $campaign = MembershipFeeCampaign::query()->lockForUpdate()->find($campaignId);

            if ($campaign === null) {
                return;
            }

            if ($campaign->status === MembershipFeeCampaignStatus::Invoiced) {
                $campaign->update(['status' => MembershipFeeCampaignStatus::Validated]);
            }

            $this->campaignLogger->record($campaign, 'campaign.invoice_draft_deleted', $actor, metadata: [
                'invoice_id' => $locked->id,
            ]);
        });
    }
}
