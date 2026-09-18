<?php

namespace App\Actions\Stock;

use App\Enums\StockCountType;
use App\Models\Product;
use App\Models\Season;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockLocation;
use App\Services\StockService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class CreateStockCountAction
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    /**
     * @param  Collection<int, Product>  $products
     */
    public function execute(
        Season $season,
        StockLocation $location,
        StockCountType $type,
        Collection $products,
    ): StockCount {
        if (! $location->active) {
            throw new LogicException('Stock count cannot be created for an inactive location.');
        }

        $stockableProducts = $products->filter(fn (Product $product) => $product->is_stockable);

        return DB::transaction(function () use ($season, $location, $type, $stockableProducts) {
            $count = StockCount::create([
                'season_id' => $season->id,
                'stock_location_id' => $location->id,
                'type' => $type,
                'status' => 'pending',
            ]);

            foreach ($stockableProducts as $product) {
                StockCountLine::create([
                    'stock_count_id' => $count->id,
                    'product_id' => $product->id,
                    'expected_quantity' => $this->stockService->getStockForProduct($product, $location),
                    'counted_quantity' => null,
                ]);
            }

            return $count;
        });
    }
}
