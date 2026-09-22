<?php

namespace App\Actions\Stock;

use App\Models\StockLocation;
use Illuminate\Support\Facades\DB;

class UpdateStockLocationAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(StockLocation $location, array $data): StockLocation
    {
        return DB::transaction(function () use ($location, $data) {
            $location->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'active' => $data['active'],
            ]);

            return $location;
        });
    }
}
