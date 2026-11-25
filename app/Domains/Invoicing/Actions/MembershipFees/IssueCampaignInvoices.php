<?php

namespace App\Domains\Invoicing\Actions\MembershipFees;

use App\Domains\Invoicing\Actions\Invoices\IssueInvoice;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\MembershipFeeCampaignStatus;
use App\Domains\Invoicing\Exceptions\MembershipFeeException;
use App\Domains\Invoicing\Models\MembershipFeeCampaign;
use App\Domains\Invoicing\Services\InvoiceBalanceCalculator;
use App\Domains\Invoicing\Services\MembershipFeeCampaignActivityLogger;
use App\Models\User;
use Throwable;

class IssueCampaignInvoices
{
    public function __construct(
        private readonly IssueInvoice $issueInvoice,
        private readonly InvoiceBalanceCalculator $balances,
        private readonly MembershipFeeCampaignActivityLogger $activityLogger,
    ) {}

    /**
     * @return array{campaign: MembershipFeeCampaign, issued: int, errors: array<int, string>}
     */
    public function execute(MembershipFeeCampaign $campaign, User $actor): array
    {
        $draftInvoices = $campaign->invoices()
            ->where('status', InvoiceStatus::Draft)
            ->orderBy('id')
            ->get();

        if ($draftInvoices->isEmpty()) {
            throw MembershipFeeException::campaignHasNoDraftInvoices();
        }

        $issued = 0;
        $errors = [];

        foreach ($draftInvoices as $invoice) {
            try {
                $issuedInvoice = $this->issueInvoice->execute($invoice, $actor);

                if ($issuedInvoice->total_cents === 0) {
                    $this->balances->refreshStatus($issuedInvoice);
                }

                $issued++;
            } catch (Throwable $exception) {
                $errors[] = "Facture #{$invoice->id} ({$invoice->recipient_name}) : {$exception->getMessage()}";
            }
        }

        $campaign->refresh();

        $hasDrafts = $campaign->invoices()
            ->where('status', InvoiceStatus::Draft)
            ->exists();

        if (! $hasDrafts && ! $campaign->hasUninvoicedMembers() && $campaign->includedMembers()->exists()) {
            $campaign->update(['status' => MembershipFeeCampaignStatus::Issued]);
        }

        $this->activityLogger->record($campaign, 'campaign.invoices_issued', $actor, metadata: [
            'issued' => $issued,
            'errors' => $errors,
        ]);

        return [
            'campaign' => $campaign->refresh(),
            'issued' => $issued,
            'errors' => $errors,
        ];
    }
}
