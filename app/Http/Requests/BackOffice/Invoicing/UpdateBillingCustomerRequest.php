<?php

namespace App\Http\Requests\BackOffice\Invoicing;

use App\Domains\Invoicing\Enums\BillingCustomerType;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBillingCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('billingCustomer')) ?? false;
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
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::requiredIf(fn (): bool => $this->input('preferred_invoice_delivery') === InvoiceDeliveryMethod::Email->value),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'preferred_invoice_delivery' => ['required', Rule::enum(InvoiceDeliveryMethod::class)],
            'sync_with_member' => ['nullable', 'boolean'],
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
            'email.required' => "L'email est obligatoire pour l'envoi par email.",
            'email.email' => "L'email n'est pas valide.",
            'preferred_invoice_delivery.required' => "Le mode d'envoi est obligatoire.",
        ];
    }
}
