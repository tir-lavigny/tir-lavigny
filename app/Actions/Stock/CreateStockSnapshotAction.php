<?php

namespace App\Actions\Stock;

use App\Models\StockLocation;
use App\Models\StockSnapshot;
use App\Models\StockSnapshotLine;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateStockSnapshotAction
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    public function execute(StockLocation $location): StockSnapshot
    {
        $snapshot = DB::transaction(function () use ($location) {
            $stock = $this->stockService->getStockForLocation($location);

            $snapshot = StockSnapshot::create([
                'stock_location_id' => $location->id,
                'snapshot_at' => now(),
            ]);

            // Use the DB-persisted created_at as the authoritative boundary.
            // This avoids any gap between the PHP now() call and the actual
            // INSERT, ensuring the boundary is exactly when the snapshot row
            // was persisted.
            $snapshot->update(['snapshot_at' => $snapshot->created_at]);

            foreach ($stock as $item) {
                StockSnapshotLine::create([
                    'stock_snapshot_id' => $snapshot->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            return $snapshot;
        });

        Log::info('Stock snapshot created', [
            'stock_snapshot_id' => $snapshot->id,
            'stock_location_id' => $location->id,
            'line_count' => $snapshot->stockSnapshotLines()->count(),
        ]);

        return $snapshot;
    }
}
