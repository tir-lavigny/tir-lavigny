<?php

namespace Tests\Unit\Services;

use App\Domains\Invoicing\Enums\InvoiceItemType;
use App\Domains\Invoicing\Services\InvoiceTotals;
use Tests\TestCase;

class InvoiceTotalsTest extends TestCase
{
    public function test_calculates_subtotal_discounts_and_total(): void
    {
        $totals = (new InvoiceTotals)->calculate([
            [
                'type' => InvoiceItemType::Fee,
                'quantity' => '1',
                'unit_price_cents' => 15000,
            ],
            [
                'type' => InvoiceItemType::Discount,
                'quantity' => '1',
                'unit_price_cents' => -3000,
            ],
        ]);

        $this->assertSame(15000, $totals['subtotal_cents']);
        $this->assertSame(-3000, $totals['discounts_cents']);
        $this->assertSame(12000, $totals['total_cents']);
        $this->assertSame([1, 2], array_column($totals['lines'], 'position'));
        $this->assertSame([15000, -3000], array_column($totals['lines'], 'total_cents'));
    }

    public function test_calculates_decimal_quantities_in_integer_cents(): void
    {
        $totals = (new InvoiceTotals)->calculate([
            [
                'type' => 'product',
                'quantity' => '2.500',
                'unit_price_cents' => 799,
            ],
        ]);

        $this->assertSame(1998, $totals['lines'][0]['total_cents']);
        $this->assertSame(1998, $totals['subtotal_cents']);
        $this->assertSame(1998, $totals['total_cents']);
    }

    public function test_adjustments_are_part_of_the_subtotal(): void
    {
        $totals = (new InvoiceTotals)->calculate([
            [
                'type' => 'fee',
                'quantity' => '1',
                'unit_price_cents' => 10000,
            ],
            [
                'type' => 'adjustment',
                'quantity' => '1',
                'unit_price_cents' => -500,
            ],
        ]);

        $this->assertSame(9500, $totals['subtotal_cents']);
        $this->assertSame(0, $totals['discounts_cents']);
        $this->assertSame(9500, $totals['total_cents']);
    }
}
