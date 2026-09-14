<?php

namespace App\Http\Requests;

use App\Enums\SwissCanton;
use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'form_uuid' => ['required', 'string', 'exists:member_registrations,form_uuid'],
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'is_swiss_citizen' => ['required', 'boolean'],
            'avs' => ['required_if:is_swiss_citizen,true', 'nullable', 'string', 'max:20', 'regex:/^756\.\d{4}\.\d{4}\.\d{2}$/'],
            'tel' => ['required', 'string', 'max:20'],
            'street' => ['required', 'string', 'max:255'],
            'npa' => ['required', 'numeric', 'digits:4'],
            'city' => ['required', 'string', 'max:255'],
            'canton' => ['required', 'string', 'in:'.SwissCanton::validationRule()],
            'country' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'avs.regex' => 'Le numéro AVS doit être au format 756.XXXX.XXXX.XX.',
            'avs.required_if' => 'Le numéro AVS est requis pour les citoyens suisses.',
        ];
    }
}
