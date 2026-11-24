<?php

namespace App\Domains\Invoicing\Actions\Invoices;

use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Events\InvoiceIssued;
use App\Domains\Invoicing\Exceptions\InvoiceNotIssuable;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\CustomerAccountLedger;
use App\Domains\Invoicing\Services\FinancialDocumentArchiver;
use App\Domains\Invoicing\Services\FinancialDocumentGenerator;
use App\Domains\Invoicing\Services\InvoiceActivityLogger;
use App\Domains\Invoicing\Services\InvoiceDraftService;
use App\Domains\Invoicing\Services\InvoiceNumberGenerator;
use App\Domains\Invoicing\Services\InvoicingSettingsService;
use App\Domains\Invoicing\Services\PaymentReferenceGenerator;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class IssueInvoice
{
    public function __construct(
        private readonly InvoiceNumberGenerator $numberGenerator,
        private readonly InvoiceDraftService $draftService,
        private readonly InvoiceActivityLogger $activityLogger,
        private readonly CustomerAccountLedger $ledger,
        private readonly InvoicingSettingsService $settings,
        private readonly PaymentReferenceGenerator $references,
        private readonly FinancialDocumentGenerator $documents,
        private readonly FinancialDocumentArchiver $archiver,
    ) {}

    public function execute(Invoice $invoice, User $actor): Invoice
    {
        $this->settings->assertConfigured();

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
            $snapshot = $this->draftService->recipientSnapshot($billingCustomer);

            if ($locked->delivery_method === InvoiceDeliveryMethod::Email && blank($snapshot['recipient_email'])) {
                throw InvoiceNotIssuable::for($locked, 'the customer has no email address for email delivery.');
            }

            $invoiceNumber = $this->numberGenerator->allocate((int) $issueDate->format('Y'));
            $paymentReference = $this->references->forInvoiceNumber($invoiceNumber);

            $locked->update([
                'invoice_number' => $invoiceNumber,
                'status' => InvoiceStatus::Issued,
                'issue_date' => $issueDate,
                'issued_at' => now(),
                'payment_reference' => $paymentReference,
                ...$this->settings->creditorSnapshot(),
                ...$snapshot,
            ]);

            $locked->load('items');

            try {
                $pdf = $this->documents->invoice($locked);
            } catch (Throwable $exception) {
                throw InvoiceNotIssuable::for($locked, 'the PDF document could not be generated. '.$exception->getMessage());
            }

            $this->archiver->queue($locked, $pdf, 'invoices', "{$invoiceNumber}.pdf");

            $this->ledger->recordInvoiceIssued($locked, $actor);
            $this->activityLogger->record($locked, 'invoice.issued', $actor, metadata: [
                'invoice_number' => $invoiceNumber,
                'payment_reference' => $paymentReference,
            ]);

            return $locked;
        });

        $issued->load('items', 'document');

        event(new InvoiceIssued($issued));

        return $issued;
    }
}
