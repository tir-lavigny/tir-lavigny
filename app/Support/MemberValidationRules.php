<?php

namespace App\Support;

use App\Enums\SwissCanton;
use App\Rules\UniqueMemberAvs;

class MemberValidationRules
{
    /**
     * Rules shared by public registration and back-office creation.
     *
     * @return array<string, mixed>
     */
    public static function create(bool $uniqueAvs = true): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'is_swiss_citizen' => ['required', 'boolean'],
            'avs' => self::avs($uniqueAvs),
            'tel' => ['required', 'string', 'max:20'],
            'street' => ['required', 'string', 'max:255'],
            'npa' => ['required', 'numeric', 'digits:4'],
            'city' => ['required', 'string', 'max:255'],
            'canton' => ['required', 'string', 'in:'.SwissCanton::validationRule()],
            'country' => ['required', 'string', 'max:100'],
        ];
    }

    /** @return array<int, mixed> */
    private static function avs(bool $uniqueAvs): array
    {
        $rules = [
            'required_if:is_swiss_citizen,true',
            'nullable',
            'string',
            'max:20',
            'regex:/^756\.\d{4}\.\d{4}\.\d{2}$/',
        ];

        if ($uniqueAvs) {
            $rules[] = new UniqueMemberAvs;
        }

        return $rules;
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'avs.regex' => 'Le numéro AVS doit être au format 756.XXXX.XXXX.XX.',
            'avs.required_if' => 'Le numéro AVS est requis pour les citoyens suisses.',
        ];
    }
}
