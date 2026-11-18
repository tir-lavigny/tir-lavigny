<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyMemberRegistrationEmailRequest extends FormRequest
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
            'code' => ['required', 'string', 'digits:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Le code de vérification est requis.',
            'code.digits' => 'Le code doit contenir 6 chiffres.',
        ];
    }
}
