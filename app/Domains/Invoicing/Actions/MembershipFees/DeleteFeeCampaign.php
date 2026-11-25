<?php

namespace App\Domains\Invoicing\Actions\MembershipFees;

use App\Domains\Invoicing\Actions\Invoices\DeleteDraftInvoice;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Exceptions\MembershipFeeException;
use App\Domains\Invoicing\Models\MembershipFeeCampaign;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteFeeCampaign
{
    public function __construct(
        private readonly DeleteDraftInvoice $deleteDraftInvoice,
    ) {}

    public function execute(MembershipFeeCampaign $campaign, User $actor): void
    {
        DB::transaction(function () use ($campaign, $actor): void {
            $locked = MembershipFeeCampaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $invoices = $locked->invoices()->lockForUpdate()->get();

            if ($invoices->contains(fn ($invoice): bool => $invoice->status !== InvoiceStatus::Draft)) {
                throw MembershipFeeException::campaignHasNonDraftInvoices();
            }

            foreach ($invoices as $invoice) {
                $this->deleteDraftInvoice->execute($invoice, $actor);
            }

            $locked->delete();
        });
    }
}
