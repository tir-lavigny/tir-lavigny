<?php

namespace App\Http\Requests\BackOffice\Invoicing;

use Illuminate\Foundation\Http\FormRequest;

class SendInvoiceEmailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAny(['send invoices', 'manage invoicing']) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invoice_ids' => ['required', 'array', 'min:1', 'max:200'],
            'invoice_ids.*' => ['required', 'integer', 'distinct', 'exists:invoices,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invoice_ids.required' => 'Sélectionnez au moins une facture.',
            'invoice_ids.array' => 'La sélection de factures est invalide.',
            'invoice_ids.min' => 'Sélectionnez au moins une facture.',
            'invoice_ids.max' => 'Vous ne pouvez pas envoyer plus de 200 factures à la fois.',
            'invoice_ids.*.exists' => 'Une des factures sélectionnées est introuvable.',
        ];
    }
}
