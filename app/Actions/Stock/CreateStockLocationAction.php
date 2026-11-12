<?php

namespace App\Actions\Stock;

use App\Models\StockLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateStockLocationAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): StockLocation
    {
        $location = DB::transaction(function () use ($data) {
            return StockLocation::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'active' => $data['active'],
            ]);
        });

        Log::info('Stock location created', [
            'stock_location_id' => $location->id,
            'name' => $location->name,
            'active' => $location->active,
        ]);

        return $location;
    }
}
