<?php

namespace App\Domains\Invoicing\Actions\Payments;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Exceptions\PaymentException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoicePayment;
use App\Domains\Invoicing\Models\Payment;
use App\Domains\Invoicing\Services\CustomerAccountLedger;
use App\Domains\Invoicing\Services\InvoiceActivityLogger;
use App\Domains\Invoicing\Services\InvoiceBalanceCalculator;
use App\Domains\Invoicing\Services\PaymentBalanceCalculator;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AllocatePayment
{
    public function __construct(
        private readonly PaymentBalanceCalculator $paymentBalances,
        private readonly InvoiceBalanceCalculator $invoiceBalances,
        private readonly CustomerAccountLedger $ledger,
        private readonly InvoiceActivityLogger $activityLogger,
    ) {}

    public function execute(
        Payment $payment,
        Invoice $invoice,
        int $amountCents,
        User $actor,
        ?string $idempotencyKey = null,
    ): InvoicePayment {
        return DB::transaction(function () use ($payment, $invoice, $amountCents, $actor, $idempotencyKey): InvoicePayment {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($idempotencyKey !== null) {
                $existing = InvoicePayment::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing !== null) {
                    if (
                        $existing->payment_id !== $lockedPayment->id
                        || $existing->invoice_id !== $invoice->id
                        || $existing->amount_cents !== $amountCents
                    ) {
                        throw PaymentException::idempotencyConflict();
                    }

                    return $existing->loadMissing('invoice', 'payment');
                }
            }

            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (! $lockedInvoice->isDistributable()) {
                throw PaymentException::invoiceNotPayable();
            }

            if ($lockedPayment->isVoided()) {
                throw PaymentException::paymentVoided();
            }

            if ($amountCents <= 0) {
                throw PaymentException::invalidAmount();
            }

            if ($amountCents > $this->paymentBalances->availableCents($lockedPayment)) {
                throw PaymentException::amountExceedsPayment();
            }

            if ($amountCents > $this->invoiceBalances->outstandingCents($lockedInvoice)) {
                throw PaymentException::amountExceedsInvoice();
            }

            $allocation = InvoicePayment::create([
                'invoice_id' => $lockedInvoice->id,
                'payment_id' => $lockedPayment->id,
                'amount_cents' => $amountCents,
                'idempotency_key' => $idempotencyKey,
            ]);

            $allocation->setRelation('invoice', $lockedInvoice);
            $allocation->setRelation('payment', $lockedPayment);

            $this->ledger->recordPaymentAllocated($allocation, $actor);

            $previousStatus = $lockedInvoice->status;
            $refreshed = $this->invoiceBalances->refreshStatus($lockedInvoice);

            $this->activityLogger->record($lockedInvoice, 'invoice.payment_allocated', $actor, metadata: [
                'payment_id' => $lockedPayment->id,
                'invoice_payment_id' => $allocation->id,
                'amount_cents' => $amountCents,
            ]);

            if ($previousStatus !== InvoiceStatus::Paid && $refreshed->status === InvoiceStatus::Paid) {
                $this->activityLogger->record($refreshed, 'invoice.paid', $actor, metadata: [
                    'payment_id' => $lockedPayment->id,
                ]);
            }

            return $allocation;
        });
    }
}
