<?php

namespace App\Http\Requests\BackOffice\Invoicing;

use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Models\Invoice;
use App\Http\Requests\BackOffice\Invoicing\Concerns\ValidatesInvoiceLines;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInvoiceRequest extends FormRequest
{
    use ValidatesInvoiceLines;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Invoice::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'billing_customer_id' => ['required', 'integer', Rule::exists('billing_customers', 'id')],
            'delivery_method' => ['required', Rule::enum(InvoiceDeliveryMethod::class)],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['required', 'date', Rule::when($this->filled('issue_date'), ['after_or_equal:issue_date'])],
            ...$this->invoiceLineRules(),
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateInvoiceTotals($validator);
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'billing_customer_id.required' => 'Le client de facturation est obligatoire.',
            'billing_customer_id.exists' => 'Le client de facturation est introuvable.',
            'due_date.required' => "L'échéance est obligatoire.",
            'due_date.after_or_equal' => "L'échéance ne peut pas être antérieure à la date d'émission.",
            'items.required' => 'La facture doit contenir au moins une ligne.',
        ];
    }
}
