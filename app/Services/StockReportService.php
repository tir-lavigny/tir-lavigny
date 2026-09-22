<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Season;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

class StockReportService
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    /**
     * @return iterable<array<string, int|string>>
     */
    public function getStockReportRows(Season $season): iterable
    {
        $locations = StockLocation::where('active', true)->orderBy('name')->get();
        $stockableProducts = Product::where('is_stockable', true)->orderBy('name')->get();

        $currentStock = $this->getCurrentStock($locations);
        $seasonActivity = $this->getSeasonActivity($season);

        foreach ($locations as $location) {
            foreach ($stockableProducts as $product) {
                $activity = $seasonActivity[$location->id][$product->id] ?? [];

                yield [
                    'Lieu' => $location->name,
                    'Produit' => $product->name,
                    'Stock actuel' => $currentStock[$location->id][$product->id] ?? 0,
                    'Ventes' => abs($activity['sale_qty'] ?? 0),
                    'Pertes' => abs($activity['loss_qty'] ?? 0),
                    'Entrées transferts' => $activity['transfer_in_qty'] ?? 0,
                    'Sorties transferts' => abs($activity['transfer_out_qty'] ?? 0),
                    'Ajustements' => $activity['adjustment_qty'] ?? 0,
                    'Retours' => $activity['return_qty'] ?? 0,
                    'Réversions' => $activity['reversal_qty'] ?? 0,
                    'Variation saison' => $activity['variation_qty'] ?? 0,
                ];
            }
        }
    }

    /**
     * @return iterable<array<string, int|string>>
     */
    public function getStockMovementsReportRows(Season $season): iterable
    {
        $movements = StockMovement::with(['stockLocation', 'product'])
            ->where('created_at', '>=', $season->start_date->startOfDay())
            ->where('created_at', '<=', $season->end_date->endOfDay())
            ->orderBy('created_at')
            ->get();

        foreach ($movements as $movement) {
            $createdAt = $movement->created_at->setTimezone('Europe/Zurich');

            yield [
                'Date' => $createdAt->format('d.m.Y'),
                'Heure' => $createdAt->format('H:i'),
                'Lieu' => $movement->stockLocation->name,
                'Produit' => $movement->product->name,
                'Type' => $this->getTypeLabel($movement->type),
                'Quantité' => $movement->quantity,
                'Motif' => $movement->reason ?? '',
                'Référence' => $this->getReferenceLabel($movement),
            ];
        }
    }

    /**
     * @param  StockLocation[]  $locations
     * @return array<int, array<int, int>>
     */
    private function getCurrentStock($locations): array
    {
        $result = [];

        foreach ($locations as $location) {
            $stocks = $this->stockService->getStockForLocation($location);
            foreach ($stocks as $stock) {
                $result[$location->id][$stock['product_id']] = $stock['quantity'];
            }
        }

        return $result;
    }

    /**
     * @return array<int, array<int, array<string, int>>>
     */
    private function getSeasonActivity(Season $season): array
    {
        $movements = StockMovement::where('created_at', '>=', $season->start_date->startOfDay())
            ->where('created_at', '<=', $season->end_date->endOfDay())
            ->select(
                'stock_location_id',
                'product_id',
                DB::raw("SUM(CASE WHEN type = 'sale' THEN quantity ELSE 0 END) as sale_qty"),
                DB::raw("SUM(CASE WHEN type = 'loss' THEN quantity ELSE 0 END) as loss_qty"),
                DB::raw("SUM(CASE WHEN type = 'transfer' AND quantity > 0 THEN quantity ELSE 0 END) as transfer_in_qty"),
                DB::raw("SUM(CASE WHEN type = 'transfer' AND quantity < 0 THEN quantity ELSE 0 END) as transfer_out_qty"),
                DB::raw("SUM(CASE WHEN type = 'adjustment' THEN quantity ELSE 0 END) as adjustment_qty"),
                DB::raw("SUM(CASE WHEN type = 'return' THEN quantity ELSE 0 END) as return_qty"),
                DB::raw("SUM(CASE WHEN type = 'reversal' THEN quantity ELSE 0 END) as reversal_qty"),
                DB::raw('SUM(quantity) as variation_qty'),
            )
            ->groupBy('stock_location_id', 'product_id')
            ->get();

        $result = [];

        foreach ($movements as $m) {
            $result[$m->stock_location_id][$m->product_id] = [
                'sale_qty' => (int) $m->sale_qty,
                'loss_qty' => (int) $m->loss_qty,
                'transfer_in_qty' => (int) $m->transfer_in_qty,
                'transfer_out_qty' => (int) $m->transfer_out_qty,
                'adjustment_qty' => (int) $m->adjustment_qty,
                'return_qty' => (int) $m->return_qty,
                'reversal_qty' => (int) $m->reversal_qty,
                'variation_qty' => (int) $m->variation_qty,
            ];
        }

        return $result;
    }

    private function getTypeLabel(StockMovementType $type): string
    {
        return match ($type) {
            StockMovementType::Initial => 'Initial',
            StockMovementType::Purchase => 'Achat',
            StockMovementType::Sale => 'Vente',
            StockMovementType::Return => 'Retour',
            StockMovementType::Transfer => 'Transfert',
            StockMovementType::Loss => 'Perte',
            StockMovementType::Adjustment => 'Ajustement',
            StockMovementType::Reversal => 'Annulation',
        };
    }

    private function getReferenceLabel(StockMovement $movement): string
    {
        if ($movement->reference_type === null || $movement->reference_id === null) {
            return '';
        }

        return match ($movement->reference_type) {
            Ticket::class => "Ticket #{$movement->reference_id}",
            default => '',
        };
    }
}
