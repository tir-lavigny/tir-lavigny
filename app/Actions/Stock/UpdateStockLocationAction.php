<?php

namespace App\Actions\Stock;

use App\Models\StockLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateStockLocationAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(StockLocation $location, array $data): StockLocation
    {
        DB::transaction(function () use ($location, $data) {
            $location->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'active' => $data['active'],
            ]);
        });

        Log::info('Stock location updated', [
            'stock_location_id' => $location->id,
            'name' => $location->name,
            'active' => $location->active,
        ]);

        return $location;
    }
}
