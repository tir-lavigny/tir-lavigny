<?php

namespace Tests\Unit\Models;

use App\Enums\StockCountType;
use App\Models\Season;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StockCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_count_belongs_to_season(): void
    {
        $count = StockCount::factory()->create();

        $this->assertInstanceOf(Season::class, $count->season);
    }

    public function test_stock_count_belongs_to_stock_location(): void
    {
        $count = StockCount::factory()->create();

        $this->assertInstanceOf(StockLocation::class, $count->stockLocation);
    }

    public function test_stock_count_has_many_stock_count_lines(): void
    {
        $count = StockCount::factory()->create();
        StockCountLine::factory()->count(3)->create(['stock_count_id' => $count->id]);

        $this->assertCount(3, $count->stockCountLines);
    }

    public function test_stock_count_type_is_cast_to_enum(): void
    {
        $count = StockCount::factory()->create([
            'type' => StockCountType::Initial,
        ]);

        $this->assertInstanceOf(StockCountType::class, $count->type);
        $this->assertEquals(StockCountType::Initial, $count->type);
    }

    public function test_stock_count_completed_at_is_cast_to_datetime(): void
    {
        $count = StockCount::factory()->completed()->create();

        $this->assertNotNull($count->completed_at);
        $this->assertInstanceOf(Carbon::class, $count->completed_at);
    }

    public function test_stock_count_has_many_stock_movements_through_morph(): void
    {
        $count = StockCount::factory()->create();
        StockMovement::factory()->create([
            'reference_type' => StockCount::class,
            'reference_id' => $count->id,
        ]);

        $this->assertCount(1, $count->stockMovements);
    }

    public function test_stock_count_factory_initial(): void
    {
        $count = StockCount::factory()->initial()->create();

        $this->assertEquals(StockCountType::Initial, $count->type);
    }

    public function test_stock_count_factory_adjustment(): void
    {
        $count = StockCount::factory()->adjustment()->create();

        $this->assertEquals(StockCountType::Adjustment, $count->type);
    }

    public function test_stock_count_factory_final(): void
    {
        $count = StockCount::factory()->final()->create();

        $this->assertEquals(StockCountType::Final, $count->type);
    }

    public function test_stock_count_factory_completed(): void
    {
        $count = StockCount::factory()->completed()->create();

        $this->assertEquals('completed', $count->status);
        $this->assertNotNull($count->completed_at);
    }

    public function test_stock_count_cannot_be_deleted(): void
    {
        $count = StockCount::factory()->create();

        $this->expectException(\LogicException::class);
        $count->delete();
    }
}
