<?php

namespace App\Http\Requests\BackOffice\Invoicing;

use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Models\MembershipFeeCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignMemberDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campaign = $this->route('membershipFeeCampaign');

        return $campaign instanceof MembershipFeeCampaign
            && ($this->user()?->can('manageMembers', $campaign) ?? false);
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
            'delivery_method.required' => 'Le mode d’envoi est obligatoire.',
            'delivery_method.enum' => 'Le mode d’envoi est invalide.',
        ];
    }
}
