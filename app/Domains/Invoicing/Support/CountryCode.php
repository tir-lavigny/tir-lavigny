<?php

namespace App\Domains\Invoicing\Support;

final class CountryCode
{
    /** @var array<string, string> */
    private const ALIASES = [
        'ch' => 'CH',
        'suisse' => 'CH',
        'schweiz' => 'CH',
        'switzerland' => 'CH',
    ];

    public static function normalize(?string $country): string
    {
        $country = trim((string) $country);

        if ($country === '') {
            return 'CH';
        }

        $normalized = mb_strtolower($country);

        return self::ALIASES[$normalized] ?? (mb_strlen($country) === 2 ? mb_strtoupper($country) : 'CH');
    }
}
