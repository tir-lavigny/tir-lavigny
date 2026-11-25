<?php

namespace App\Http\Requests\BackOffice\Invoicing\Concerns;

use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Support\Money;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesPayment
{
    /**
     * @return array<string, mixed>
     */
    protected function paymentRules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_date' => ['required', 'date'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function validatePaymentAmount(Validator $validator): void
    {
        if ($validator->errors()->has('amount')) {
            return;
        }

        if ((Money::toCents($this->input('amount')) ?? 0) <= 0) {
            $validator->errors()->add('amount', 'Le montant doit être positif.');
        }
    }

    protected function preparePaymentForValidation(): void
    {
        $this->merge([
            'reference' => filled($this->input('reference')) ? trim((string) $this->input('reference')) : null,
            'notes' => filled($this->input('notes')) ? trim((string) $this->input('notes')) : null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function paymentMessages(): array
    {
        return [
            'amount.required' => 'Le montant est obligatoire.',
            'amount.numeric' => 'Le montant doit être un nombre.',
            'amount.gt' => 'Le montant doit être positif.',
            'payment_date.required' => 'La date du paiement est obligatoire.',
            'payment_date.date' => 'La date du paiement est invalide.',
            'method.required' => 'Le moyen de paiement est obligatoire.',
            'method.enum' => 'Le moyen de paiement est invalide.',
            'reference.max' => 'La référence ne peut pas dépasser 255 caractères.',
            'notes.max' => 'Les notes ne peuvent pas dépasser 2000 caractères.',
        ];
    }
}
