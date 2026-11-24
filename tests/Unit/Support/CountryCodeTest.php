<?php

namespace Tests\Unit\Support;

use App\Domains\Invoicing\Support\CountryCode;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CountryCodeTest extends TestCase
{
    /**
     * @return array<string, array{string|null, string}>
     */
    public static function countries(): array
    {
        return [
            'swiss french label' => ['Suisse', 'CH'],
            'swiss german label' => ['Schweiz', 'CH'],
            'swiss english label' => ['Switzerland', 'CH'],
            'iso code' => ['ch', 'CH'],
            'empty' => [null, 'CH'],
            'other iso code' => ['fr', 'FR'],
            'unknown label falls back to switzerland' => ['France', 'CH'],
        ];
    }

    #[DataProvider('countries')]
    public function test_normalizes_country_to_iso_code(?string $country, string $expected): void
    {
        $this->assertSame($expected, CountryCode::normalize($country));
    }
}
