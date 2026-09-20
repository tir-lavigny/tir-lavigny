<?php

namespace App\Actions\Stock;

use App\Models\StockLocation;
use App\Models\StockSnapshot;
use App\Models\StockSnapshotLine;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

class CreateStockSnapshotAction
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    public function execute(StockLocation $location): StockSnapshot
    {
        return DB::transaction(function () use ($location) {
            $stock = $this->stockService->getStockForLocation($location);

            $snapshotAt = now();

            $snapshot = StockSnapshot::create([
                'stock_location_id' => $location->id,
                'snapshot_at' => $snapshotAt,
            ]);

            foreach ($stock as $item) {
                StockSnapshotLine::create([
                    'stock_snapshot_id' => $snapshot->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            return $snapshot;
        });
    }
}
