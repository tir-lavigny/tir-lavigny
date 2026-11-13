<?php

namespace App\Support;

use Illuminate\Support\Str;

class PiiMasker
{
    public static function email(?string $email): ?string
    {
        if ($email === null || $email === '') {
            return null;
        }

        $local = Str::before($email, '@');
        $domain = Str::after($email, '@');

        if ($domain === '' || $domain === $email) {
            return self::generic($email);
        }

        return Str::substr($local, 0, 1).'***@'.$domain;
    }

    public static function avs(?string $avs): ?string
    {
        if ($avs === null || $avs === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $avs) ?? '';

        if (strlen($digits) < 5) {
            return self::generic($avs);
        }

        return substr($digits, 0, 3).'.***.***.'.substr($digits, -2);
    }

    private static function generic(string $value): string
    {
        return Str::substr($value, 0, 1).'***';
    }
}
