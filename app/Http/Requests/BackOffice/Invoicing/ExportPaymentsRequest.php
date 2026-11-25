<?php

namespace App\Http\Requests\BackOffice\Invoicing;

use App\Domains\Invoicing\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportPaymentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAny(['view payments', 'manage invoicing']) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'format' => ['nullable', Rule::in(['csv', 'xlsx'])],
            'search' => ['nullable', 'string', 'max:255'],
            'method' => ['nullable', Rule::in(array_column(PaymentMethod::cases(), 'value'))],
            'status' => ['nullable', Rule::in(['active', 'voided'])],
            'allocation_status' => ['nullable', Rule::in(['unallocated', 'partially_allocated', 'fully_allocated'])],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'format.in' => 'Le format doit être CSV ou Excel.',
            'method.in' => 'Le moyen de paiement demandé est invalide.',
            'status.in' => 'Le statut demandé est invalide.',
            'allocation_status.in' => 'Le statut d’imputation demandé est invalide.',
            'year.between' => 'L’année demandée est invalide.',
        ];
    }
}
