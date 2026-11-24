<?php

namespace App\Http\Requests\BackOffice\Invoicing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('apply', $this->route('creditNote')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'integer', Rule::exists('invoices', 'id')],
            'amount' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invoice_id.required' => 'La facture cible est obligatoire.',
            'amount.required' => 'Le montant est obligatoire.',
            'amount.gt' => 'Le montant doit être positif.',
        ];
    }
}
