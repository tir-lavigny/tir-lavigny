<?php

namespace App\Http\Requests\BackOffice;

use App\Support\MemberValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage members') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...MemberValidationRules::create(),
            'status' => ['required', Rule::in(['active', 'passive'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return MemberValidationRules::messages();
    }
}
