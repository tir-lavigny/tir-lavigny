<?php

namespace Tests\Unit\Enums;

use App\Enums\StockCountType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockCountTypeTest extends TestCase
{
    #[Test]
    public function test_has_all_3_types(): void
    {
        $this->assertCount(3, StockCountType::cases());
    }

    #[Test]
    public function test_all_case_values_are_strings(): void
    {
        foreach (StockCountType::cases() as $type) {
            $this->assertIsString($type->value);
        }
    }

    #[Test]
    public function test_all_case_values_are_lowercase(): void
    {
        foreach (StockCountType::cases() as $type) {
            $this->assertEquals(strtolower($type->value), $type->value);
        }
    }

    #[Test]
    public function test_initial_case(): void
    {
        $this->assertEquals('initial', StockCountType::Initial->value);
    }

    #[Test]
    public function test_adjustment_case(): void
    {
        $this->assertEquals('adjustment', StockCountType::Adjustment->value);
    }

    #[Test]
    public function test_final_case(): void
    {
        $this->assertEquals('final', StockCountType::Final->value);
    }
}
