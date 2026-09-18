<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Season;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class StockService
{
    /**
     * Create a stock movement.
     *
     * @param  int  $quantity  Positive = stock enters, negative = stock leaves
     * @param  Model|null  $reference  Polymorphic reference (Ticket, StockCount, etc.)
     */
    public function addMovement(
        Product $product,
        StockLocation $location,
        int $quantity,
        StockMovementType $type,
        ?Model $reference = null,
        ?Season $season = null,
        ?string $reason = null,
    ): StockMovement {
        return StockMovement::create([
            'product_id' => $product->id,
            'stock_location_id' => $location->id,
            'quantity' => $quantity,
            'type' => $type,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference ? $reference->getKey() : null,
            'season_id' => $season ? $season->id : null,
            'reason' => $reason,
        ]);
    }

    /**
     * Create a compensating stock movement that reverses an existing movement.
     *
     * The original movement is never modified or deleted.
     * The reversal creates a new movement with the opposite quantity.
     *
     * @throws LogicException if the movement has already been reversed
     */
    public function revert(StockMovement $movement): StockMovement
    {
        $this->guardAgainstDoubleRevert($movement);

        return $this->addMovement(
            product: $movement->product,
            location: $movement->stockLocation,
            quantity: -$movement->quantity,
            type: StockMovementType::Reversal,
            reference: $movement->reference,
            season: $movement->season,
            reason: "Reversal of stock movement #{$movement->id}",
        );
    }

    /**
     * Deduct stock for a ticket by creating negative stock movements
     * for each stockable ticket item.
     *
     * The ticket must already be persisted (have an id) before calling this.
     * The ticket's stock location is required (non-nullable).
     */
    public function deductForTicket(Ticket $ticket): void
    {
        $season = $ticket->operatingDay?->season;

        foreach ($ticket->ticketItems as $item) {
            if (! $item->product->is_stockable) {
                continue;
            }
            $this->addMovement(
                product: $item->product,
                location: $ticket->stockLocation,
                quantity: -$item->quantity,
                type: StockMovementType::Sale,
                reference: $ticket,
                season: $season,
            );
        }
    }

    /**
     * Create compensating stock movements for a refunded ticket.
     *
     * Finds all stock movements that reference this ticket
     * and creates reversal movements with opposite quantities.
     */
    public function reverseForTicket(Ticket $ticket): void
    {
        $movements = StockMovement::where('reference_type', Ticket::class)
            ->where('reference_id', $ticket->id)
            ->get();

        foreach ($movements as $movement) {
            $this->revert($movement);
        }
    }

    /**
     * Get the current stock quantity for a product at a location.
     */
    public function getStockForProduct(Product $product, StockLocation $location): int
    {
        return (int) StockMovement::where('product_id', $product->id)
            ->where('stock_location_id', $location->id)
            ->sum('quantity');
    }

    /**
     * Check if a movement has already been reversed.
     *
     * @throws LogicException if already reversed
     */
    private function guardAgainstDoubleRevert(StockMovement $movement): void
    {
        $alreadyReversed = StockMovement::where('reference_type', $movement->reference_type)
            ->where('reference_id', $movement->reference_id)
            ->where('product_id', $movement->product_id)
            ->where('stock_location_id', $movement->stock_location_id)
            ->where('type', StockMovementType::Reversal)
            ->where('reason', 'LIKE', "Reversal of stock movement #{$movement->id}")
            ->exists();

        if ($alreadyReversed) {
            throw new LogicException("Stock movement #{$movement->id} has already been reversed.");
        }
    }
}
