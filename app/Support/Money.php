<?php

namespace App\Support;

/**
 * Conversion helpers between the franc amounts used by forms and the API and
 * the integer cents amounts used to store and compute money.
 *
 * Monetary values are never kept in a PHP float internally: amounts enter the
 * application as decimals (form input, legacy column) and are converted once
 * at the boundary.
 */
final class Money
{
    /**
     * Convert a decimal franc amount (int, float or numeric string) to cents.
     *
     * Strings are parsed digit by digit so that values such as "0.07" never
     * pass through a binary float.
     */
    public static function toCents(int|float|string|null $amount): ?int
    {
        if ($amount === null) {
            return null;
        }

        if (is_int($amount)) {
            return $amount * 100;
        }

        $normalized = trim(str_replace([',', ' '], ['', ''], is_float($amount) ? number_format($amount, 4, '.', '') : $amount));

        if ($normalized === '' || $normalized === '-' || $normalized === '+') {
            return null;
        }

        if (! preg_match('/^([+-]?)(\d*)(?:\.(\d+))?$/', $normalized, $matches) || ($matches[2] === '' && ! isset($matches[3]))) {
            return is_numeric($normalized) ? (int) round(((float) $normalized) * 100) : null;
        }

        $decimals = $matches[3] ?? '';
        $cents = (int) ($matches[2] === '' ? '0' : $matches[2]) * 100
            + (int) substr(str_pad($decimals, 2, '0'), 0, 2);

        if (strlen($decimals) > 2 && (int) $decimals[2] >= 5) {
            $cents++;
        }

        return $matches[1] === '-' && $cents !== 0 ? -$cents : $cents;
    }

    /**
     * Format cents as a two-decimal string ("12550" => "125.50").
     */
    public static function fromCents(?int $cents): ?string
    {
        if ($cents === null) {
            return null;
        }

        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Cents as a float, for display and JSON payloads only.
     */
    public static function toFloat(?int $cents): ?float
    {
        return $cents === null ? null : round($cents / 100, 2);
    }

    /**
     * Apply a percentage to an amount in cents, rounded half up to the cent.
     */
    public static function percentOf(int $cents, float|string|null $percent): int
    {
        if ($percent === null) {
            return 0;
        }

        return (int) round($cents * ((float) $percent) / 100);
    }
}
