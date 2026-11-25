<?php

namespace App\Http\Requests\BackOffice\Invoicing;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AllocatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAny(['create payments', 'manage invoicing']) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'integer', Rule::exists('invoices', 'id')],
            'amount' => ['required', 'numeric', 'gt:0'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('amount')) {
                    return;
                }

                if ((Money::toCents($this->input('amount')) ?? 0) <= 0) {
                    $validator->errors()->add('amount', 'Le montant doit être positif.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invoice_id.required' => 'La facture cible est obligatoire.',
            'invoice_id.exists' => 'La facture cible est introuvable.',
            'amount.required' => 'Le montant est obligatoire.',
            'amount.gt' => 'Le montant doit être positif.',
            'idempotency_key.required' => 'La clé de sécurité de l’imputation est manquante.',
            'idempotency_key.uuid' => 'La clé de sécurité de l’imputation est invalide.',
        ];
    }
}
