<?php

namespace Tests\Unit\Services;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Season;
use App\Models\StockLocation;
use App\Models\Ticket;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StockService;
    }

    // --- addMovement() ---

    public function test_add_movement_creates_stock_movement(): void
    {
        $product = Product::factory()->create();
        $location = StockLocation::factory()->create();
        $season = Season::factory()->create();

        $movement = $this->service->addMovement(
            product: $product,
            location: $location,
            quantity: 10,
            type: StockMovementType::Purchase,
            season: $season,
            reason: 'Initial stock',
        );

        $this->assertDatabaseHas('stock_movements', [
            'id' => $movement->id,
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'quantity' => 10,
            'type' => StockMovementType::Purchase,
            'season_id' => $season->id,
            'reason' => 'Initial stock',
        ]);
    }

    public function test_add_movement_with_reference(): void
    {
        $product = Product::factory()->create();
        $location = StockLocation::factory()->create();
        $ticket = Ticket::factory()->create();

        $movement = $this->service->addMovement(
            product: $product,
            location: $location,
            quantity: -3,
            type: StockMovementType::Sale,
            reference: $ticket,
        );

        $this->assertDatabaseHas('stock_movements', [
            'id' => $movement->id,
            'reference_type' => Ticket::class,
            'reference_id' => $ticket->id,
        ]);
    }

    public function test_add_movement_with_null_reference(): void
    {
        $product = Product::factory()->create();
        $location = StockLocation::factory()->create();

        $movement = $this->service->addMovement(
            product: $product,
            location: $location,
            quantity: 5,
            type: StockMovementType::Initial,
        );

        $this->assertDatabaseHas('stock_movements', [
            'id' => $movement->id,
            'reference_type' => null,
            'reference_id' => null,
        ]);
    }

    // --- revert() ---

    public function test_revert_creates_compensating_movement(): void
    {
        $product = Product::factory()->create();
        $location = StockLocation::factory()->create();
        $season = Season::factory()->create();

        $original = $this->service->addMovement(
            product: $product,
            location: $location,
            quantity: -3,
            type: StockMovementType::Sale,
            season: $season,
            reason: 'Sale',
        );

        $reversal = $this->service->revert($original);

        $this->assertDatabaseHas('stock_movements', [
            'id' => $reversal->id,
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'quantity' => 3,
            'type' => StockMovementType::Reversal,
            'season_id' => $season->id,
            'reason' => "Reversal of stock movement #{$original->id}",
        ]);
    }

    public function test_revert_preserves_reference(): void
    {
        $product = Product::factory()->create();
        $location = StockLocation::factory()->create();
        $ticket = Ticket::factory()->create();

        $original = $this->service->addMovement(
            product: $product,
            location: $location,
            quantity: -5,
            type: StockMovementType::Sale,
            reference: $ticket,
        );

        $reversal = $this->service->revert($original);

        $this->assertDatabaseHas('stock_movements', [
            'id' => $reversal->id,
            'reference_type' => Ticket::class,
            'reference_id' => $ticket->id,
        ]);
    }

    public function test_revert_does_not_modify_original(): void
    {
        $product = Product::factory()->create();
        $location = StockLocation::factory()->create();

        $original = $this->service->addMovement(
            product: $product,
            location: $location,
            quantity: -3,
            type: StockMovementType::Sale,
            reason: 'Sale',
        );

        $this->service->revert($original);

        $original->refresh();

        $this->assertEquals(-3, $original->quantity);
        $this->assertEquals(StockMovementType::Sale, $original->type);
        $this->assertEquals('Sale', $original->reason);
    }

    public function test_revert_throws_when_already_reversed(): void
    {
        $product = Product::factory()->create();
        $location = StockLocation::factory()->create();

        $original = $this->service->addMovement(
            product: $product,
            location: $location,
            quantity: -3,
            type: StockMovementType::Sale,
        );

        $this->service->revert($original);

        $this->expectException(LogicException::class);
        $this->service->revert($original);
    }

    // --- deductForTicket() ---

    public function test_deduct_for_ticket_creates_sale_movements(): void
    {
        $product = Product::factory()->create();
        $location = StockLocation::factory()->create();
        $ticket = Ticket::factory()->create([
            'stock_location_id' => $location->id,
        ]);

        $ticket->ticketItems()->create([
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 5.00,
            'subtotal' => 15.00,
        ]);

        $this->service->deductForTicket($ticket);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'quantity' => -3,
            'type' => StockMovementType::Sale,
            'reference_type' => Ticket::class,
            'reference_id' => $ticket->id,
        ]);
    }

    public function test_deduct_for_ticket_skips_non_stockable_products(): void
    {
        $product = Product::factory()->create(['is_stockable' => false]);
        $location = StockLocation::factory()->create();
        $ticket = Ticket::factory()->create([
            'stock_location_id' => $location->id,
        ]);

        $ticket->ticketItems()->create([
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 5.00,
            'subtotal' => 15.00,
        ]);

        $this->service->deductForTicket($ticket);

        $this->assertDatabaseCount('stock_movements', 0);
    }

    // --- reverseForTicket() ---

    public function test_reverse_for_ticket_creates_return_movements(): void
    {
        $product = Product::factory()->create();
        $location = StockLocation::factory()->create();
        $ticket = Ticket::factory()->create([
            'stock_location_id' => $location->id,
        ]);

        $ticket->ticketItems()->create([
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 5.00,
            'subtotal' => 15.00,
        ]);

        $this->service->deductForTicket($ticket);

        $this->service->reverseForTicket($ticket);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'quantity' => 3,
            'type' => StockMovementType::Reversal,
            'reference_type' => Ticket::class,
            'reference_id' => $ticket->id,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'quantity' => -3,
            'type' => StockMovementType::Sale,
            'reference_type' => Ticket::class,
            'reference_id' => $ticket->id,
        ]);
    }

    public function test_reverse_for_ticket_creates_no_movements_when_none_exist(): void
    {
        $location = StockLocation::factory()->create();
        $ticket = Ticket::factory()->create([
            'stock_location_id' => $location->id,
        ]);

        $this->service->reverseForTicket($ticket);

        $this->assertDatabaseCount('stock_movements', 0);
    }
}
