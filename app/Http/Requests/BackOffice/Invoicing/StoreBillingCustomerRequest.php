<?php

namespace App\Http\Requests\BackOffice\Invoicing;

use App\Domains\Invoicing\Enums\BillingCustomerType;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Models\BillingCustomer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBillingCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', BillingCustomer::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(BillingCustomerType::class)],
            'first_name' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn (): bool => $this->input('type') === BillingCustomerType::Individual->value),
            ],
            'last_name' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn (): bool => $this->input('type') === BillingCustomerType::Individual->value),
            ],
            'organization_name' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn (): bool => $this->input('type') === BillingCustomerType::Organization->value),
            ],
            'address' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'size:2', 'alpha'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'preferred_invoice_delivery' => ['required', Rule::enum(InvoiceDeliveryMethod::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Le type de client est obligatoire.',
            'type.enum' => 'Le type de client est invalide.',
            'first_name.required' => 'Le prénom est obligatoire pour un particulier.',
            'last_name.required' => 'Le nom est obligatoire pour un particulier.',
            'organization_name.required' => "Le nom de l'organisation est obligatoire.",
            'address.required' => "L'adresse est obligatoire.",
            'postal_code.required' => 'Le code postal est obligatoire.',
            'city.required' => 'La ville est obligatoire.',
            'country.required' => 'Le pays est obligatoire.',
            'country.size' => 'Le pays doit être un code à deux lettres.',
            'preferred_invoice_delivery.required' => "Le mode d'envoi est obligatoire.",
        ];
    }
}
