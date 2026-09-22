<?php

namespace App\Http\Resources;

use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read StockMovement $resource
 */
class StockMovementResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'quantity' => $this->quantity,
            'stock_location_id' => $this->stock_location_id,
            'product_id' => $this->product_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'stockLocation' => $this->whenLoaded('stockLocation'),
            'product' => $this->whenLoaded('product'),
        ];
    }
}
