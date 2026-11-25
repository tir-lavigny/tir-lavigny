<?php

namespace App\Domains\Invoicing\Actions\Invoices;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Events\InvoiceCancelled;
use App\Domains\Invoicing\Exceptions\InvoiceNotCancellable;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\CustomerAccountLedger;
use App\Domains\Invoicing\Services\InvoiceActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CancelInvoice
{
    public function __construct(
        private readonly InvoiceActivityLogger $activityLogger,
        private readonly CustomerAccountLedger $ledger,
    ) {}

    public function execute(Invoice $invoice, string $reason, User $actor): Invoice
    {
        $reason = trim($reason);

        $cancelled = DB::transaction(function () use ($invoice, $reason, $actor): Invoice {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($locked->status !== InvoiceStatus::Issued) {
                throw InvoiceNotCancellable::for($locked, 'only unpaid issued invoices can be cancelled.');
            }

            if ($reason === '') {
                throw InvoiceNotCancellable::for($locked, 'a cancellation reason is required.');
            }

            if ($locked->activePaymentAllocations()->exists()) {
                throw InvoiceNotCancellable::for($locked, 'payments have been allocated.');
            }

            $locked->update([
                'status' => InvoiceStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => $reason,
            ]);

            $this->ledger->recordInvoiceCancelled($locked, $actor);
            $this->activityLogger->record($locked, 'invoice.cancelled', $actor, metadata: [
                'reason' => $reason,
            ]);

            return $locked->load('items');
        });

        event(new InvoiceCancelled($cancelled));

        return $cancelled;
    }
}
