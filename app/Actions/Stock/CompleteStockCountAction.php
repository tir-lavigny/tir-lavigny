<?php

namespace App\Actions\Stock;

use App\Enums\StockMovementType;
use App\Models\StockCount;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use LogicException;

class CompleteStockCountAction
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    public function execute(StockCount $count): StockCount
    {
        if ($count->status === 'completed') {
            throw new LogicException('This stock count has already been completed.');
        }

        $lines = $count->stockCountLines;

        foreach ($lines as $line) {
            if ($line->counted_quantity === null) {
                throw new LogicException("Stock count line for product #{$line->product_id} has not been counted yet.");
            }
        }

        return DB::transaction(function () use ($count, $lines) {
            foreach ($lines as $line) {
                $difference = $line->counted_quantity - $line->expected_quantity;

                if ($difference !== 0) {
                    $this->stockService->addMovement(
                        product: $line->product,
                        location: $count->stockLocation,
                        quantity: $difference,
                        type: StockMovementType::Adjustment,
                        reference: $count,
                        season: $count->season,
                    );
                }
            }

            $count->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            return $count;
        });
    }
}
