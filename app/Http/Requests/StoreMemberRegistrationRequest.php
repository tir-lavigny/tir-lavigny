<?php

namespace App\Http\Requests;

use App\Support\MemberValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'form_uuid' => ['required', 'string', 'exists:member_registrations,form_uuid'],
            ...MemberValidationRules::create(uniqueAvs: false),
        ];
    }

    public function messages(): array
    {
        return MemberValidationRules::messages();
    }
}
