<?php

namespace App\Domains\Invoicing\Actions\Invoices;

use App\Domains\Invoicing\Enums\Currency;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\InvoiceType;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoiceActivityLogger;
use App\Domains\Invoicing\Services\InvoiceDraftService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateInvoice
{
    public function __construct(
        private readonly InvoiceDraftService $draftService,
        private readonly InvoiceActivityLogger $activityLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, User $actor): Invoice
    {
        return DB::transaction(function () use ($data, $actor): Invoice {
            $billingCustomer = BillingCustomer::query()->whereKey((int) $data['billing_customer_id'])->firstOrFail();
            $prepared = $this->draftService->prepareItems($data['items']);

            $invoice = Invoice::create([
                'billing_customer_id' => $billingCustomer->id,
                'type' => $data['type'] ?? InvoiceType::OneOff,
                'status' => InvoiceStatus::Draft,
                'delivery_method' => $data['delivery_method'],
                'issue_date' => $data['issue_date'] ?? null,
                'due_date' => $data['due_date'],
                'currency' => Currency::CHF,
                'subtotal_cents' => $prepared['subtotal_cents'],
                'total_cents' => $prepared['total_cents'],
                ...$this->draftService->recipientSnapshot($billingCustomer),
                'created_by' => $actor->id,
            ]);

            $this->draftService->createItems($invoice, $prepared['lines']);
            $this->activityLogger->record($invoice, 'invoice.created', $actor);

            return $invoice->load('items');
        });
    }
}
