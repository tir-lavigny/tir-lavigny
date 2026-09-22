<?php

namespace App\Actions\Stock;

use App\Models\StockArchivedMovement;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\StockSnapshot;
use Illuminate\Support\Facades\DB;

class CompactStockHistoryAction
{
    public function __construct(
        private readonly CreateStockSnapshotAction $createSnapshotAction,
    ) {}

    /**
     * Create a snapshot of current stock, then archive all movements
     * that occurred before the snapshot timestamp.
     *
     * The snapshot becomes the new baseline. Archived movements are
     * preserved in stock_archived_movements for historical reconstruction.
     *
     * Uses raw DB queries to bypass the Eloquent deleting guard on StockMovement,
     * since this is a controlled compaction operation, not an accidental deletion.
     */
    public function execute(StockLocation $location): StockSnapshot
    {
        return DB::transaction(function () use ($location) {
            // Lock all movements for this location before calculating stock.
            // This prevents a concurrent movement from being committed with
            // created_at <= snapshot.created_at but not visible to the stock
            // calculation (MVCC gap), which would cause silent stock loss.
            // The gap lock also blocks concurrent inserts for this location
            // until the transaction commits.
            StockMovement::where('stock_location_id', $location->id)
                ->lockForUpdate()
                ->get();

            $snapshot = $this->createSnapshotAction->execute($location);

            // Use the snapshot's persisted created_at as the cutoff.
            // Movements at or before this timestamp are represented in the
            // snapshot and must be archived. Movements after remain active.
            $movements = StockMovement::where('stock_location_id', $location->id)
                ->where('created_at', '<=', $snapshot->created_at)
                ->get();

            foreach ($movements as $movement) {
                StockArchivedMovement::create([
                    'stock_location_id' => $movement->stock_location_id,
                    'product_id' => $movement->product_id,
                    'season_id' => $movement->season_id,
                    'quantity' => $movement->quantity,
                    'type' => $movement->type,
                    'reference_type' => $movement->reference_type,
                    'reference_id' => $movement->reference_id,
                    'reason' => $movement->reason,
                    'original_created_at' => $movement->created_at,
                    'original_updated_at' => $movement->updated_at,
                ]);

                DB::table('stock_movements')->where('id', $movement->id)->delete();
            }

            return $snapshot;
        });
    }
}
