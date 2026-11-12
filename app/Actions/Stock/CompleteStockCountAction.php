<?php

namespace App\Actions\Stock;

use App\Enums\StockMovementType;
use App\Models\StockCount;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        $adjustedLines = 0;
        $totalDifference = 0;

        $count = DB::transaction(function () use ($count, $lines, &$adjustedLines, &$totalDifference) {
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

                    $adjustedLines++;
                    $totalDifference += $difference;
                }
            }

            $count->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            return $count;
        });

        Log::info('Stock count completed', [
            'stock_count_id' => $count->id,
            'season_id' => $count->season_id,
            'stock_location_id' => $count->stock_location_id,
            'line_count' => $lines->count(),
            'adjusted_lines' => $adjustedLines,
            'total_difference' => $totalDifference,
        ]);

        return $count;
    }
}
