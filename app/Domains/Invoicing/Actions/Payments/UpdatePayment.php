<?php

namespace App\Domains\Invoicing\Actions\Payments;

use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Domains\Invoicing\Exceptions\PaymentException;
use App\Domains\Invoicing\Models\Payment;
use Illuminate\Support\Facades\DB;

class UpdatePayment
{
    /**
     * @param  array{
     *     amount_cents: int,
     *     method: string,
     *     payment_date: string,
     *     reference?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function execute(Payment $payment, array $data): Payment
    {
        return DB::transaction(function () use ($payment, $data): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->isVoided()) {
                throw PaymentException::paymentNotEditable();
            }

            if ($data['amount_cents'] <= 0) {
                throw PaymentException::invalidAmount();
            }

            $amountChanged = $data['amount_cents'] !== $locked->amount_cents;

            if ($amountChanged && $locked->allocations()->whereNull('reversed_at')->exists()) {
                throw PaymentException::editAmountWithActiveAllocations();
            }

            $locked->update([
                'amount_cents' => $data['amount_cents'],
                'method' => PaymentMethod::from($data['method']),
                'payment_date' => $data['payment_date'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            return $locked->refresh();
        });
    }
}
