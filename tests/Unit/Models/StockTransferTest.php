<?php

namespace Tests\Unit\Models;

use App\Models\Season;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_transfer_belongs_to_from_location(): void
    {
        $transfer = StockTransfer::factory()->create();

        $this->assertInstanceOf(StockLocation::class, $transfer->fromLocation);
    }

    public function test_stock_transfer_belongs_to_to_location(): void
    {
        $transfer = StockTransfer::factory()->create();

        $this->assertInstanceOf(StockLocation::class, $transfer->toLocation);
    }

    public function test_stock_transfer_belongs_to_season(): void
    {
        $transfer = StockTransfer::factory()->create();

        $this->assertInstanceOf(Season::class, $transfer->season);
    }

    public function test_stock_transfer_can_have_null_season(): void
    {
        $transfer = StockTransfer::factory()->withoutSeason()->create();

        $this->assertNull($transfer->season);
    }

    public function test_stock_transfer_has_many_stock_transfer_lines(): void
    {
        $transfer = StockTransfer::factory()->create();
        StockTransferLine::factory()->count(3)->create(['stock_transfer_id' => $transfer->id]);

        $this->assertCount(3, $transfer->stockTransferLines);
    }

    public function test_stock_transfer_has_many_stock_movements_through_morph(): void
    {
        $transfer = StockTransfer::factory()->create();
        StockMovement::factory()->create([
            'reference_type' => StockTransfer::class,
            'reference_id' => $transfer->id,
        ]);

        $this->assertCount(1, $transfer->stockMovements);
    }

    public function test_stock_transfer_from_and_to_locations_are_different(): void
    {
        $from = StockLocation::factory()->create();
        $to = StockLocation::factory()->create();
        $transfer = StockTransfer::factory()->create([
            'from_location_id' => $from->id,
            'to_location_id' => $to->id,
        ]);

        $this->assertNotEquals($transfer->fromLocation->id, $transfer->toLocation->id);
    }

    public function test_stock_transfer_cannot_be_deleted(): void
    {
        $transfer = StockTransfer::factory()->create();

        $this->expectException(\LogicException::class);
        $transfer->delete();
    }
}