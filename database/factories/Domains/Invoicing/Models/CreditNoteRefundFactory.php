<?php

namespace Database\Factories\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CreditNoteRefund;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditNoteRefund>
 */
class CreditNoteRefundFactory extends Factory
{
    protected $model = CreditNoteRefund::class;

    public function definition(): array
    {
        return [
            'credit_note_id' => CreditNote::factory(),
            'amount_cents' => fake()->numberBetween(100, 10000),
            'refund_date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'payment_method' => PaymentMethod::BankTransfer,
            'reference' => null,
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }
}
