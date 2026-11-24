<?php

namespace Tests\Unit\Support;

use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    /** @return array<string, array{int|float|string|null, int|null}> */
    public static function amounts(): array
    {
        return [
            'integer francs' => [125, 12500],
            'float francs' => [125.5, 12550],
            'numeric string' => ['125.50', 12550],
            'one cent' => ['0.01', 1],
            'sub-cent rounds up' => ['0.005', 1],
            'sub-cent rounds down' => ['0.004', 0],
            'negative amount' => ['-25.50', -2550],
            'comma decimal separator' => ['1,50', 150],
            'comma decimal with thousands dot' => ['1,234.56', 123456],
            'swiss apostrophe thousands' => ["1'234.56", 123456],
            'thousands space' => ['1 234.56', 123456],
            'float precision noise' => [0.1 + 0.2, 30],
            'zero' => [0, 0],
            'zero string' => ['0.00', 0],
            'null' => [null, null],
            'empty string' => ['', null],
        ];
    }

    #[DataProvider('amounts')]
    public function test_to_cents_converts_decimal_amounts(int|float|string|null $amount, ?int $expected): void
    {
        $this->assertSame($expected, Money::toCents($amount));
    }

    public function test_from_cents_formats_two_decimals(): void
    {
        $this->assertSame('125.50', Money::fromCents(12550));
        $this->assertSame('0.07', Money::fromCents(7));
        $this->assertSame('0.00', Money::fromCents(0));
        $this->assertSame('-25.50', Money::fromCents(-2550));
        $this->assertNull(Money::fromCents(null));
    }

    public function test_to_float_keeps_two_decimals(): void
    {
        $this->assertSame(125.5, Money::toFloat(12550));
        $this->assertSame(-25.5, Money::toFloat(-2550));
        $this->assertNull(Money::toFloat(null));
    }

    public function test_percent_of_rounds_to_the_nearest_cent(): void
    {
        $this->assertSame(1000, Money::percentOf(10000, 10));
        $this->assertSame(333, Money::percentOf(1000, 33.3));
        $this->assertSame(675, Money::percentOf(675, 100));
        $this->assertSame(0, Money::percentOf(10000, null));
        $this->assertSame(-100, Money::percentOf(-1000, 10));
        $this->assertSame(8, Money::percentOf(1000, 0.75)); // 7.5 cents rounds half up
    }

    public function test_to_thousandths_converts_decimal_quantities(): void
    {
        $this->assertSame(1000, Money::toThousandths(1));
        $this->assertSame(1250, Money::toThousandths('1.25'));
        $this->assertSame(1250, Money::toThousandths('1,25'));
        $this->assertSame(1235, Money::toThousandths('1.2345'));
        $this->assertSame(0, Money::toThousandths('0'));
        $this->assertNull(Money::toThousandths(null));
    }

    public function test_multiply_by_quantity_rounds_to_nearest_cent(): void
    {
        $this->assertSame(250, Money::multiplyByQuantity(100, '2.5'));
        $this->assertSame(333, Money::multiplyByQuantity(1000, '0.333'));
        $this->assertSame(-1250, Money::multiplyByQuantity(-1000, '1.25'));
        $this->assertSame(2, Money::multiplyByQuantity(3, '0.5'));
    }

    public function test_round_trip_is_lossless(): void
    {
        foreach ([0, 1, 7, 99, 100, 12550, -2550, 9999999999] as $cents) {
            $this->assertSame($cents, Money::toCents(Money::fromCents($cents)));
        }
    }
}
