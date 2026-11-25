<?php

namespace App\Http\Requests\BackOffice\Invoicing;

use App\Domains\Invoicing\Models\MembershipFeeType;
use App\MemberStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMembershipFeeTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', MembershipFeeType::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('membership_fee_types', 'name')],
            'member_status' => ['required', Rule::enum(MemberStatus::class), Rule::unique('membership_fee_types', 'member_status')],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom du type de cotisation est obligatoire.',
            'name.unique' => 'Ce nom de type de cotisation existe déjà.',
            'member_status.required' => 'Le statut de membre est obligatoire.',
            'member_status.enum' => 'Le statut de membre est invalide.',
            'member_status.unique' => 'Un type de cotisation existe déjà pour ce statut.',
            'amount.required' => 'Le montant est obligatoire.',
            'amount.numeric' => 'Le montant doit être un nombre.',
            'amount.gt' => 'Le montant doit être supérieur à zéro.',
        ];
    }
}
