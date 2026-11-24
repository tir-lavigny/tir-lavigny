<?php

namespace App\Http\Requests\BackOffice\Invoicing;

use App\Domains\Invoicing\Services\InvoicingSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateInvoicingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage invoicing') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'creditor_name' => ['required', 'string', 'max:70'],
            'creditor_address' => ['required', 'string', 'max:70'],
            'creditor_postal_code' => ['required', 'string', 'max:16'],
            'creditor_city' => ['required', 'string', 'max:35'],
            'creditor_country' => ['required', 'string', 'size:2', Rule::in(['CH', 'LI'])],
            'creditor_iban' => ['required', 'string', 'max:34'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $errors = app(InvoicingSettingsService::class)->validationErrors($this->validated());

                foreach ($errors as $field => $message) {
                    $validator->errors()->add($field, $message);
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'creditor_iban' => app(InvoicingSettingsService::class)->normalizeIban((string) $this->input('creditor_iban')),
            'creditor_country' => strtoupper((string) $this->input('creditor_country')),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'creditor_name.required' => 'Le nom du créancier est obligatoire.',
            'creditor_address.required' => 'L’adresse du créancier est obligatoire.',
            'creditor_postal_code.required' => 'Le code postal du créancier est obligatoire.',
            'creditor_city.required' => 'La localité du créancier est obligatoire.',
            'creditor_country.required' => 'Le pays du créancier est obligatoire.',
            'creditor_country.in' => 'Le pays du créancier doit être la Suisse ou le Liechtenstein.',
            'creditor_iban.required' => 'L’IBAN du créancier est obligatoire.',
        ];
    }
}
