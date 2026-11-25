<?php

namespace App\Http\Requests\BackOffice\Invoicing;

use App\Domains\Invoicing\Models\MembershipFeeCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMembershipFeeCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', MembershipFeeCampaign::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100', Rule::unique('membership_fee_campaigns', 'year')],
            'name' => ['required', 'string', 'max:255'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'year.required' => "L'année est obligatoire.",
            'year.unique' => 'Une campagne existe déjà pour cette année.',
            'year.min' => "L'année doit être comprise entre 2000 et 2100.",
            'year.max' => "L'année doit être comprise entre 2000 et 2100.",
            'name.required' => 'Le nom de la campagne est obligatoire.',
            'issue_date.required' => "La date d'émission est obligatoire.",
            'due_date.required' => "L'échéance est obligatoire.",
            'due_date.after_or_equal' => "L'échéance ne peut pas être antérieure à la date d'émission.",
        ];
    }
}
