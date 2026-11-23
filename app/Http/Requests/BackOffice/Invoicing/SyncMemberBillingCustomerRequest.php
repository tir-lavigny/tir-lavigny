<?php

namespace App\Http\Requests\BackOffice\Invoicing;

use App\Domains\Invoicing\Models\BillingCustomer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncMemberBillingCustomerRequest extends FormRequest
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
            'member_id' => [
                'required',
                'integer',
                Rule::exists('members', 'id'),
                Rule::unique('billing_customers', 'member_id'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'member_id.required' => 'Le membre est obligatoire.',
            'member_id.exists' => 'Le membre sélectionné est introuvable.',
            'member_id.unique' => 'Ce membre possède déjà un client de facturation.',
        ];
    }
}
