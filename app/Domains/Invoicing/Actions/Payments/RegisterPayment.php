<?php

namespace App\Domains\Invoicing\Actions\Payments;

use App\Domains\Invoicing\Enums\Currency;
use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Domains\Invoicing\Exceptions\PaymentException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterPayment
{
    public function __construct(
        private readonly AllocatePayment $allocatePayment,
    ) {}

    /**
     * @param  array{
     *     amount_cents: int,
     *     method: string,
     *     payment_date: string,
     *     reference?: string|null,
     *     notes?: string|null,
     *     idempotency_key: string
     * }  $data
     */
    public function execute(array $data, User $actor, ?Invoice $allocateTo = null): Payment
    {
        return DB::transaction(function () use ($data, $actor, $allocateTo): Payment {
            if ($data['amount_cents'] <= 0) {
                throw PaymentException::invalidAmount();
            }

            $payment = Payment::query()->firstOrCreate(
                ['idempotency_key' => $data['idempotency_key']],
                [
                    'amount_cents' => $data['amount_cents'],
                    'currency' => Currency::CHF,
                    'method' => PaymentMethod::from((string) $data['method']),
                    'payment_date' => $data['payment_date'],
                    'reference' => $data['reference'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $actor->id,
                    'voided_at' => null,
                    'voided_by' => null,
                    'void_reason' => null,
                ],
            );

            if ($payment->wasRecentlyCreated && $allocateTo !== null) {
                $this->allocatePayment->execute($payment, $allocateTo, $payment->amount_cents, $actor);
            }

            return $payment->refresh();
        });
    }
}
