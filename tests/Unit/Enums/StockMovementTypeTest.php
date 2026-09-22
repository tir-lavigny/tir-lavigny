<?php

namespace Tests\Unit\Enums;

use App\Enums\StockMovementType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockMovementTypeTest extends TestCase
{
    #[Test]
    public function test_has_all_8_types(): void
    {
        $this->assertCount(8, StockMovementType::cases());
    }

    #[Test]
    public function test_all_case_values_are_strings(): void
    {
        foreach (StockMovementType::cases() as $type) {
            $this->assertIsString($type->value);
        }
    }

    #[Test]
    public function test_all_case_values_are_lowercase(): void
    {
        foreach (StockMovementType::cases() as $type) {
            $this->assertEquals(strtolower($type->value), $type->value);
        }
    }

    #[Test]
    public function test_initial_case(): void
    {
        $this->assertEquals('initial', StockMovementType::Initial->value);
    }

    #[Test]
    public function test_purchase_case(): void
    {
        $this->assertEquals('purchase', StockMovementType::Purchase->value);
    }

    #[Test]
    public function test_sale_case(): void
    {
        $this->assertEquals('sale', StockMovementType::Sale->value);
    }

    #[Test]
    public function test_return_case(): void
    {
        $this->assertEquals('return', StockMovementType::Return->value);
    }

    #[Test]
    public function test_transfer_case(): void
    {
        $this->assertEquals('transfer', StockMovementType::Transfer->value);
    }

    #[Test]
    public function test_loss_case(): void
    {
        $this->assertEquals('loss', StockMovementType::Loss->value);
    }

    #[Test]
    public function test_adjustment_case(): void
    {
        $this->assertEquals('adjustment', StockMovementType::Adjustment->value);
    }

    #[Test]
    public function test_reversal_case(): void
    {
        $this->assertEquals('reversal', StockMovementType::Reversal->value);
    }
}
