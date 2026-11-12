<?php

namespace App\Actions\Stock;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Season;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

class RecordStockLossAction
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    public function execute(
        ?Season $season,
        StockLocation $location,
        Product $product,
        int $quantity,
        ?string $reason = null,
    ): StockMovement {
        if (! $location->active) {
            throw new LogicException('Stock loss cannot be recorded for an inactive location.');
        }

        if (! $product->is_stockable) {
            throw new LogicException('Stock loss cannot be recorded for a non-stockable product.');
        }

        if ($quantity <= 0) {
            throw new LogicException('Stock loss quantity must be greater than zero.');
        }

        $movement = DB::transaction(function () use ($season, $location, $product, $quantity, $reason) {
            return $this->stockService->addMovement(
                product: $product,
                location: $location,
                quantity: -$quantity,
                type: StockMovementType::Loss,
                season: $season,
                reason: $reason,
            );
        });

        Log::info('Stock loss recorded', [
            'stock_movement_id' => $movement->id,
            'season_id' => $season?->id,
            'stock_location_id' => $location->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
        ]);

        return $movement;
    }
}
