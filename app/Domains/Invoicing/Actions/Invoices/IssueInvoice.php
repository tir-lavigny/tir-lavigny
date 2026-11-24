<?php

namespace App\Domains\Invoicing\Actions\Invoices;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Events\InvoiceIssued;
use App\Domains\Invoicing\Exceptions\InvoiceNotIssuable;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\CustomerAccountLedger;
use App\Domains\Invoicing\Services\InvoiceActivityLogger;
use App\Domains\Invoicing\Services\InvoiceDraftService;
use App\Domains\Invoicing\Services\InvoiceNumberGenerator;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class IssueInvoice
{
    public function __construct(
        private readonly InvoiceNumberGenerator $numberGenerator,
        private readonly InvoiceDraftService $draftService,
        private readonly InvoiceActivityLogger $activityLogger,
        private readonly CustomerAccountLedger $ledger,
    ) {}

    public function execute(Invoice $invoice, User $actor): Invoice
    {
        $issued = DB::transaction(function () use ($invoice, $actor): Invoice {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($locked->status !== InvoiceStatus::Draft) {
                throw InvoiceNotIssuable::for($locked, 'the invoice is not a draft.');
            }

            if (! $locked->items()->exists()) {
                throw InvoiceNotIssuable::for($locked, 'the invoice has no lines.');
            }

            if ($locked->subtotal_cents <= 0) {
                throw InvoiceNotIssuable::for($locked, 'the subtotal must be positive.');
            }

            if ($locked->total_cents < 0) {
                throw InvoiceNotIssuable::for($locked, 'the total cannot be negative.');
            }

            if ($locked->due_date === null) {
                throw InvoiceNotIssuable::for($locked, 'the due date is required.');
            }

            $issueDate = $locked->issue_date ?? now()->startOfDay();

            if ($locked->due_date->lt($issueDate)) {
                throw InvoiceNotIssuable::for($locked, 'the due date is before the issue date.');
            }

            $billingCustomer = $locked->billingCustomer()->firstOrFail();
            $invoiceNumber = $this->numberGenerator->allocate((int) $issueDate->format('Y'));

            $locked->update([
                'invoice_number' => $invoiceNumber,
                'status' => InvoiceStatus::Issued,
                'issue_date' => $issueDate,
                'issued_at' => now(),
                ...$this->draftService->recipientSnapshot($billingCustomer),
            ]);

            $this->ledger->recordInvoiceIssued($locked, $actor);
            $this->activityLogger->record($locked, 'invoice.issued', $actor, metadata: [
                'invoice_number' => $invoiceNumber,
            ]);

            return $locked->load('items');
        });

        event(new InvoiceIssued($issued));

        return $issued;
    }
}
