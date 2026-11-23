<?php

namespace App\Support;

/**
 * Conversion helpers between the franc amounts used by forms and the API and
 * the integer cents amounts used to store and compute money.
 *
 * Monetary values are never kept in a PHP float internally: amounts enter the
 * application as decimals (form input) and are converted once at the boundary.
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

        $normalized = self::normalize($amount);

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
     * Normalize a user-facing amount to a plain "1234.56" string.
     *
     * Swiss-formatted input is accepted: thin/spaced thousands separators are
     * dropped, and a comma counts as the decimal separator unless a dot is
     * already present (then it is a thousands separator).
     */
    private static function normalize(float|string $amount): string
    {
        $normalized = trim(is_float($amount) ? number_format($amount, 4, '.', '') : $amount);
        $normalized = str_replace([' ', "'"], '', $normalized);

        if (! str_contains($normalized, ',')) {
            return $normalized;
        }

        return str_contains($normalized, '.')
            ? str_replace(',', '', $normalized)
            : str_replace(',', '.', $normalized);
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
     * Apply a percentage to an amount in cents, rounded half away from zero.
     *
     * The percentage is converted to basis points (33.33% => 3333) and applied
     * with integer arithmetic only.
     */
    public static function percentOf(int $cents, float|string|null $percent): int
    {
        if ($percent === null) {
            return 0;
        }

        $basisPoints = self::toCents($percent) ?? 0;

        if ($basisPoints === 0 || $cents === 0) {
            return 0;
        }

        $product = $cents * $basisPoints;
        $rounded = intdiv(abs($product) + 5000, 10000);

        return $product < 0 ? -$rounded : $rounded;
    }
}
