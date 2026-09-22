<?php

namespace Tests\Unit\Models;

use App\Models\StockSnapshot;
use App\Models\StockSnapshotLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class StockSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_snapshot_belongs_to_stock_location(): void
    {
        $snapshot = StockSnapshot::factory()->create();

        $this->assertNotNull($snapshot->stockLocation);
    }

    public function test_stock_snapshot_has_many_stock_snapshot_lines(): void
    {
        $snapshot = StockSnapshot::factory()->create();
        StockSnapshotLine::factory()->count(3)->create(['stock_snapshot_id' => $snapshot->id]);

        $this->assertCount(3, $snapshot->stockSnapshotLines);
    }

    public function test_stock_snapshot_cannot_be_deleted(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('cannot be deleted');

        $snapshot = StockSnapshot::factory()->create();
        $snapshot->delete();
    }
}
