<?php

namespace App\Support;

class Avs
{
    public static function looksLike(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        if (preg_match('/^756\.\d{4}\.\d{4}\.\d{2}$/', $value) === 1) {
            return true;
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        return strlen($digits) === 13;
    }
}
