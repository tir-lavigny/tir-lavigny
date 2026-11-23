<?php

namespace Database\Factories\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\Currency;
use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Domains\Invoicing\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'amount_cents' => fake()->numberBetween(100, 50000),
            'currency' => Currency::CHF,
            'method' => PaymentMethod::BankTransfer,
            'payment_date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'reference' => null,
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }
}
