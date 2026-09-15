<?php

namespace App\Http\Resources;

use App\Models\TicketItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read TicketItem $resource
 */
class TicketItemResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'ticket_id' => $this->ticket_id,
            'voucher_id' => $this->voucher_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'subtotal' => $this->subtotal,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'product' => new ProductResource($this->whenLoaded('product')),
            'voucher' => new VoucherResource($this->whenLoaded('voucher')),
        ];
    }
}
