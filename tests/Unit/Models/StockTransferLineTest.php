<?php

namespace Tests\Unit\Models;

use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferLineTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_transfer_line_belongs_to_stock_transfer(): void
    {
        $line = StockTransferLine::factory()->create();

        $this->assertInstanceOf(StockTransfer::class, $line->stockTransfer);
    }

    public function test_stock_transfer_line_belongs_to_product(): void
    {
        $line = StockTransferLine::factory()->create();

        $this->assertInstanceOf(Product::class, $line->product);
    }

    public function test_stock_transfer_line_quantity_is_unsigned_integer(): void
    {
        $line = StockTransferLine::factory()->create(['quantity' => 15]);

        $this->assertIsInt($line->quantity);
        $this->assertGreaterThan(0, $line->quantity);
        $this->assertEquals(15, $line->quantity);
    }

}