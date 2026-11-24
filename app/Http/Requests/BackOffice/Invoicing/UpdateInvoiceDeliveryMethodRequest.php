<?php

namespace App\Http\Requests\BackOffice\Invoicing;

use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceDeliveryMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('changeDeliveryMethod', $this->route('invoice')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'delivery_method' => ['required', Rule::enum(InvoiceDeliveryMethod::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'delivery_method.required' => 'Le mode de distribution est obligatoire.',
        ];
    }
}
