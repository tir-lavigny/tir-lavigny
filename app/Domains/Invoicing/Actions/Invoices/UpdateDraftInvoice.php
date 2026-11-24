<?php

namespace App\Domains\Invoicing\Actions\Invoices;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\InvoiceType;
use App\Domains\Invoicing\Exceptions\InvoiceNotDraft;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoiceActivityLogger;
use App\Domains\Invoicing\Services\InvoiceDraftService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateDraftInvoice
{
    public function __construct(
        private readonly InvoiceDraftService $draftService,
        private readonly InvoiceActivityLogger $activityLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Invoice $invoice, array $data, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $data, $actor): Invoice {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($locked->status !== InvoiceStatus::Draft) {
                throw InvoiceNotDraft::for($locked);
            }

            $billingCustomer = BillingCustomer::query()->whereKey((int) $data['billing_customer_id'])->firstOrFail();
            $prepared = $this->draftService->prepareItems($data['items']);

            $locked->update([
                'billing_customer_id' => $billingCustomer->id,
                'type' => $data['type'] ?? $locked->type ?? InvoiceType::OneOff,
                'delivery_method' => $data['delivery_method'],
                'issue_date' => $data['issue_date'] ?? null,
                'due_date' => $data['due_date'],
                'subtotal_cents' => $prepared['subtotal_cents'],
                'total_cents' => $prepared['total_cents'],
                ...$this->draftService->recipientSnapshot($billingCustomer),
            ]);

            $this->draftService->replaceItems($locked, $prepared['lines']);
            $this->activityLogger->record($locked, 'invoice.updated', $actor);

            return $locked->load('items');
        });
    }
}
