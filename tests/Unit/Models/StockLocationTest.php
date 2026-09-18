<?php

namespace Tests\Unit\Models;

use App\Models\StockCount;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_location_has_many_stock_movements(): void
    {
        $location = StockLocation::factory()->create();
        StockMovement::factory()->count(3)->create(['stock_location_id' => $location->id]);

        $this->assertCount(3, $location->stockMovements);
    }

    public function test_stock_location_has_many_stock_counts(): void
    {
        $location = StockLocation::factory()->create();
        StockCount::factory()->count(2)->create(['stock_location_id' => $location->id]);

        $this->assertCount(2, $location->stockCounts);
    }

    public function test_stock_location_has_many_from_transfers(): void
    {
        $location = StockLocation::factory()->create();
        StockTransfer::factory()->count(2)->create(['from_location_id' => $location->id]);

        $this->assertCount(2, $location->fromTransfers);
    }

    public function test_stock_location_has_many_to_transfers(): void
    {
        $location = StockLocation::factory()->create();
        StockTransfer::factory()->count(2)->create(['to_location_id' => $location->id]);

        $this->assertCount(2, $location->toTransfers);
    }

    public function test_stock_location_has_many_tickets(): void
    {
        $location = StockLocation::factory()->create();
        Ticket::factory()->count(3)->create(['stock_location_id' => $location->id]);

        $this->assertCount(3, $location->tickets);
    }

    public function test_stock_location_is_active_by_default(): void
    {
        $location = StockLocation::factory()->create();

        $this->assertTrue($location->active);
    }

    public function test_stock_location_can_be_inactive(): void
    {
        $location = StockLocation::factory()->inactive()->create();

        $this->assertFalse($location->active);
    }
}
