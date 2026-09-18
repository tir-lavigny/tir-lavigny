<?php

namespace Tests\Unit\Models;

use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockCountLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockCountLineTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_count_line_belongs_to_stock_count(): void
    {
        $line = StockCountLine::factory()->create();

        $this->assertInstanceOf(StockCount::class, $line->stockCount);
    }

    public function test_stock_count_line_belongs_to_product(): void
    {
        $line = StockCountLine::factory()->create();

        $this->assertInstanceOf(Product::class, $line->product);
    }

    public function test_stock_count_line_expected_quantity_is_integer(): void
    {
        $line = StockCountLine::factory()->create(['expected_quantity' => 25]);

        $this->assertIsInt($line->expected_quantity);
        $this->assertEquals(25, $line->expected_quantity);
    }

    public function test_stock_count_line_counted_quantity_is_integer(): void
    {
        $line = StockCountLine::factory()->create(['counted_quantity' => 30]);

        $this->assertIsInt($line->counted_quantity);
        $this->assertEquals(30, $line->counted_quantity);
    }

    public function test_stock_count_line_factory_matched(): void
    {
        $line = StockCountLine::factory()->matched()->create([
            'expected_quantity' => 10,
        ]);

        $this->assertEquals(10, $line->counted_quantity);
    }

    public function test_stock_count_line_factory_with_difference(): void
    {
        $line = StockCountLine::factory()->withDifference()->create([
            'expected_quantity' => 10,
        ]);

        $this->assertNotEquals(10, $line->counted_quantity);
    }
}