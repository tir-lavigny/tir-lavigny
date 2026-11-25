<?php

namespace App\Domains\Invoicing\Actions\Payments;

use App\Domains\Invoicing\Exceptions\PaymentException;
use App\Domains\Invoicing\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class VoidPayment
{
    public function execute(Payment $payment, string $reason, User $actor): Payment
    {
        $reason = trim($reason);

        return DB::transaction(function () use ($payment, $reason, $actor): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->isVoided()) {
                return $locked;
            }

            if ($reason === '') {
                throw PaymentException::reasonRequired();
            }

            if ($locked->allocations()->whereNull('reversed_at')->exists()) {
                throw PaymentException::voidWithActiveAllocations();
            }

            $locked->update([
                'voided_at' => now(),
                'voided_by' => $actor->id,
                'void_reason' => $reason,
            ]);

            return $locked->refresh();
        });
    }
}
