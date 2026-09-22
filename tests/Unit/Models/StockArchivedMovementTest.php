<?php

namespace Tests\Unit\Models;

use App\Models\StockArchivedMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class StockArchivedMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_archived_movement_belongs_to_stock_location(): void
    {
        $archived = StockArchivedMovement::factory()->create();

        $this->assertNotNull($archived->stockLocation);
    }

    public function test_archived_movement_belongs_to_product(): void
    {
        $archived = StockArchivedMovement::factory()->create();

        $this->assertNotNull($archived->product);
    }

    public function test_archived_movement_belongs_to_season(): void
    {
        $archived = StockArchivedMovement::factory()->create();

        $this->assertNotNull($archived->season);
    }

    public function test_archived_movement_preserves_original_timestamps(): void
    {
        $archived = StockArchivedMovement::factory()->create();

        $this->assertNotNull($archived->original_created_at);
        $this->assertNotNull($archived->original_updated_at);
    }

    public function test_archived_movement_cannot_be_deleted(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('cannot be deleted');

        $archived = StockArchivedMovement::factory()->create();
        $archived->delete();
    }
}
