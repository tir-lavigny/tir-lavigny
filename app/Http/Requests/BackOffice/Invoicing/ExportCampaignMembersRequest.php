<?php

namespace App\Http\Requests\BackOffice\Invoicing;

use App\Domains\Invoicing\Enums\CampaignMemberStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportCampaignMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAny(['manage invoicing', 'manage membership fees']) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'format' => ['nullable', Rule::in(['csv', 'xlsx'])],
            'search' => ['nullable', 'string', 'max:255'],
            'member_status' => ['nullable', Rule::in(array_column(CampaignMemberStatus::cases(), 'value'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'format.in' => 'Le format doit être CSV ou Excel.',
            'member_status.in' => 'Le statut de membre demandé est invalide.',
        ];
    }
}
