<?php

namespace Tests\Unit\Models;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Season;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_movement_belongs_to_stock_location(): void
    {
        $movement = StockMovement::factory()->create();

        $this->assertInstanceOf(StockLocation::class, $movement->stockLocation);
    }

    public function test_stock_movement_belongs_to_product(): void
    {
        $movement = StockMovement::factory()->create();

        $this->assertInstanceOf(Product::class, $movement->product);
    }

    public function test_stock_movement_belongs_to_season(): void
    {
        $movement = StockMovement::factory()->create();

        $this->assertInstanceOf(Season::class, $movement->season);
    }

    public function test_stock_movement_can_have_null_season(): void
    {
        $movement = StockMovement::factory()->withoutSeason()->create();

        $this->assertNull($movement->season);
    }

    public function test_stock_movement_has_polymorphic_reference(): void
    {
        $ticket = Ticket::factory()->create();
        $movement = StockMovement::factory()->create([
            'reference_type' => Ticket::class,
            'reference_id' => $ticket->id,
        ]);

        $this->assertInstanceOf(Ticket::class, $movement->reference);
        $this->assertEquals($ticket->id, $movement->reference->id);
    }

    public function test_stock_movement_reference_can_be_null(): void
    {
        $movement = StockMovement::factory()->create([
            'reference_type' => null,
            'reference_id' => null,
        ]);

        $this->assertNull($movement->reference);
    }

    public function test_stock_movement_type_is_cast_to_enum(): void
    {
        $movement = StockMovement::factory()->create([
            'type' => StockMovementType::Purchase,
        ]);

        $this->assertInstanceOf(StockMovementType::class, $movement->type);
        $this->assertEquals(StockMovementType::Purchase, $movement->type);
    }

    public function test_stock_movement_quantity_is_signed_integer(): void
    {
        $positive = StockMovement::factory()->create(['quantity' => 10]);
        $negative = StockMovement::factory()->create(['quantity' => -5]);

        $this->assertEquals(10, $positive->quantity);
        $this->assertEquals(-5, $negative->quantity);
    }

    public function test_stock_movement_factory_initial(): void
    {
        $movement = StockMovement::factory()->initial()->create();

        $this->assertEquals(StockMovementType::Initial, $movement->type);
    }

    public function test_stock_movement_factory_purchase(): void
    {
        $movement = StockMovement::factory()->purchase()->create();

        $this->assertEquals(StockMovementType::Purchase, $movement->type);
        $this->assertGreaterThan(0, $movement->quantity);
    }

    public function test_stock_movement_factory_sale(): void
    {
        $movement = StockMovement::factory()->sale()->create();

        $this->assertEquals(StockMovementType::Sale, $movement->type);
        $this->assertLessThan(0, $movement->quantity);
    }

    public function test_stock_movement_factory_loss(): void
    {
        $movement = StockMovement::factory()->loss()->create();

        $this->assertEquals(StockMovementType::Loss, $movement->type);
        $this->assertLessThan(0, $movement->quantity);
    }

    public function test_stock_movement_cannot_be_deleted(): void
    {
        $movement = StockMovement::factory()->create();

        $this->expectException(\LogicException::class);
        $movement->delete();
    }
}
