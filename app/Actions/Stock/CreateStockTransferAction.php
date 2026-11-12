<?php

namespace App\Actions\Stock;

use App\Enums\StockMovementType;
use App\Exceptions\StockTransferInsufficientStock;
use App\Models\Product;
use App\Models\Season;
use App\Models\StockLocation;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Services\StockService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

class CreateStockTransferAction
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    /**
     * @param  Collection<int, array{product_id: int, quantity: int}>  $items
     */
    public function execute(
        ?Season $season,
        StockLocation $fromLocation,
        StockLocation $toLocation,
        Collection $items,
        ?string $reason = null,
    ): StockTransfer {
        if ($fromLocation->id === $toLocation->id) {
            throw new LogicException('Source and destination locations must be different.');
        }

        if (! $fromLocation->active) {
            throw new LogicException('Transfer cannot originate from an inactive location.');
        }

        if (! $toLocation->active) {
            throw new LogicException('Transfer cannot be sent to an inactive location.');
        }

        $productIds = $items->pluck('product_id');
        $uniqueProductIds = $productIds->unique();

        if ($productIds->count() !== $uniqueProductIds->count()) {
            throw new LogicException('Transfer contains duplicate products.');
        }

        $products = Product::whereIn('id', $productIds)->get();

        if ($products->count() !== $productIds->count()) {
            throw new LogicException('Transfer references products that do not exist.');
        }

        foreach ($items as $item) {
            if ($item['quantity'] <= 0) {
                throw new LogicException('Transfer quantities must be greater than zero.');
            }

            $product = $products->firstWhere('id', $item['product_id']);

            if (! $product->is_stockable) {
                throw new LogicException("Product #{$item['product_id']} is not stockable.");
            }

            $currentStock = $this->stockService->getStockForProduct($product, $fromLocation);

            if ($currentStock - $item['quantity'] < 0) {
                throw new StockTransferInsufficientStock(
                    product: $product,
                    available: $currentStock,
                    requested: $item['quantity'],
                );
            }
        }

        $transfer = DB::transaction(function () use ($season, $fromLocation, $toLocation, $items, $reason, $products) {
            $transfer = StockTransfer::create([
                'from_location_id' => $fromLocation->id,
                'to_location_id' => $toLocation->id,
                'season_id' => $season?->id,
                'reason' => $reason,
            ]);

            foreach ($items as $item) {
                $product = $products->firstWhere('id', $item['product_id']);

                StockTransferLine::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);

                $this->stockService->addMovement(
                    product: $product,
                    location: $fromLocation,
                    quantity: -$item['quantity'],
                    type: StockMovementType::Transfer,
                    reference: $transfer,
                    season: $season,
                    reason: $reason,
                );

                $this->stockService->addMovement(
                    product: $product,
                    location: $toLocation,
                    quantity: $item['quantity'],
                    type: StockMovementType::Transfer,
                    reference: $transfer,
                    season: $season,
                    reason: $reason,
                );
            }

            return $transfer;
        });

        Log::info('Stock transfer created', [
            'stock_transfer_id' => $transfer->id,
            'season_id' => $season?->id,
            'from_location_id' => $fromLocation->id,
            'to_location_id' => $toLocation->id,
            'line_count' => $items->count(),
            'total_quantity' => $items->sum('quantity'),
        ]);

        return $transfer;
    }
}
