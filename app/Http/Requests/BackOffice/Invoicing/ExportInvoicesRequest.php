<?php

namespace App\Http\Requests\BackOffice\Invoicing;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\InvoiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAny(['view invoices', 'manage invoicing']) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'format' => ['nullable', Rule::in(['csv', 'xlsx'])],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(array_column(InvoiceStatus::cases(), 'value'))],
            'type' => ['nullable', Rule::in(array_column(InvoiceType::cases(), 'value'))],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'overdue' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'format.in' => 'Le format doit être CSV ou Excel.',
            'status.in' => 'Le statut demandé est invalide.',
            'type.in' => 'Le type demandé est invalide.',
            'year.between' => 'L’année demandée est invalide.',
        ];
    }
}
