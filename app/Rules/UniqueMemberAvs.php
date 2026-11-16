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
        if (self::exists($value, $this->ignoreMemberId)) {
            $fail('Ce numéro AVS est déjà utilisé par un autre membre.');
        }
    }

    public static function exists(mixed $value, ?int $ignoreMemberId = null): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        $normalized = preg_replace('/\D/', '', $value) ?? '';

        if ($normalized === '') {
            return false;
        }

        return Member::withTrashed()
            ->where('avs_hash', app(MemberDataCipher::class)->hash($normalized))
            ->when($ignoreMemberId !== null, fn ($query) => $query->whereKeyNot($ignoreMemberId))
            ->exists();
    }
}
