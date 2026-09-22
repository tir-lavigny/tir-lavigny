<?php

namespace App\Actions\Stock;

use App\Models\StockLocation;
use Illuminate\Support\Facades\DB;

class CreateStockLocationAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): StockLocation
    {
        return DB::transaction(function () use ($data) {
            return StockLocation::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'active' => $data['active'],
            ]);
        });
    }
}
