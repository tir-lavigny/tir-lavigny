<?php

namespace App\Domains\Invoicing\Actions\Payments;

use App\Domains\Invoicing\Exceptions\PaymentException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoicePayment;
use App\Domains\Invoicing\Models\Payment;
use App\Domains\Invoicing\Services\CustomerAccountLedger;
use App\Domains\Invoicing\Services\InvoiceActivityLogger;
use App\Domains\Invoicing\Services\InvoiceBalanceCalculator;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReversePaymentAllocation
{
    public function __construct(
        private readonly CustomerAccountLedger $ledger,
        private readonly InvoiceBalanceCalculator $invoiceBalances,
        private readonly InvoiceActivityLogger $activityLogger,
    ) {}

    public function execute(InvoicePayment $allocation, string $reason, User $actor): InvoicePayment
    {
        $reason = trim($reason);

        return DB::transaction(function () use ($allocation, $reason, $actor): InvoicePayment {
            $locked = InvoicePayment::query()->lockForUpdate()->findOrFail($allocation->id);

            if ($locked->isReversed()) {
                return $locked;
            }

            if ($reason === '') {
                throw PaymentException::reasonRequired();
            }

            $payment = Payment::query()->lockForUpdate()->findOrFail($locked->payment_id);
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($locked->invoice_id);

            $locked->update([
                'reversed_at' => now(),
                'reversed_by' => $actor->id,
                'reversal_reason' => $reason,
            ]);

            $locked->setRelation('invoice', $invoice);
            $locked->setRelation('payment', $payment);

            $this->ledger->recordPaymentAllocationReversed($locked, $actor);
            $this->invoiceBalances->refreshStatus($invoice);

            $this->activityLogger->record($invoice, 'invoice.payment_allocation_reversed', $actor, metadata: [
                'payment_id' => $payment->id,
                'invoice_payment_id' => $locked->id,
                'amount_cents' => $locked->amount_cents,
                'reason' => $reason,
            ]);

            return $locked->refresh();
        });
    }
}
