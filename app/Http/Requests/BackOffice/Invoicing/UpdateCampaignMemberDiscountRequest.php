<?php

namespace App\Http\Requests\BackOffice\Invoicing;

use App\Domains\Invoicing\Models\MembershipFeeCampaign;
use App\Domains\Invoicing\Models\MembershipFeeCampaignMember;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCampaignMemberDiscountRequest extends FormRequest
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
            'discount' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('discount')) {
                    return;
                }

                $campaignMember = $this->route('campaignMember');

                if (! $campaignMember instanceof MembershipFeeCampaignMember) {
                    return;
                }

                $discountCents = Money::toCents($this->input('discount'));

                if ($discountCents === null) {
                    $validator->errors()->add('discount', 'Le montant de la remise est invalide.');

                    return;
                }

                if ($discountCents > $campaignMember->base_amount_cents) {
                    $validator->errors()->add('discount', 'La remise ne peut pas dépasser le montant de la cotisation.');
                }

                if ($discountCents === $campaignMember->base_amount_cents && blank($this->input('reason'))) {
                    $validator->errors()->add('reason', 'Un motif est obligatoire pour une exonération totale.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'discount.required' => 'Le montant de la remise est obligatoire.',
            'discount.numeric' => 'Le montant de la remise doit être un nombre.',
            'discount.min' => 'La remise ne peut pas être négative.',
        ];
    }
}
