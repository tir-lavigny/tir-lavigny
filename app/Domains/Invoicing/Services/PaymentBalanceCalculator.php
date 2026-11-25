<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Models\InvoicePayment;
use App\Domains\Invoicing\Models\Payment;

class PaymentBalanceCalculator
{
    public function allocatedCents(Payment $payment): int
    {
        return (int) InvoicePayment::query()
            ->where('payment_id', $payment->id)
            ->whereNull('reversed_at')
            ->sum('amount_cents');
    }

    public function availableCents(Payment $payment): int
    {
        if ($payment->isVoided()) {
            return 0;
        }

        return max(0, $payment->amount_cents - $this->allocatedCents($payment));
    }
}
