<?php

namespace App\Rules;

use App\Models\Member;
use App\Support\MemberDataCipher;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueMemberAvs implements ValidationRule
{
    public function __construct(
        private readonly ?int $ignoreMemberId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $normalized = preg_replace('/\D/', '', $value) ?? '';

        if ($normalized === '') {
            return;
        }

        $exists = Member::withTrashed()
            ->where('avs_hash', app(MemberDataCipher::class)->hash($normalized))
            ->when($this->ignoreMemberId !== null, fn ($query) => $query->whereKeyNot($this->ignoreMemberId))
            ->exists();

        if ($exists) {
            $fail('Ce numéro AVS est déjà utilisé par un autre membre.');
        }
    }
}
