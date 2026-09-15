<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Ticket $resource
 */
class TicketResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'total' => $this->total,
            'payment_method' => $this->payment_method,
            'operating_day_id' => $this->operating_day_id,
            'member_id' => $this->member_id,
            'sold_at' => $this->sold_at,
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'ticketItems' => TicketItemResource::collection($this->whenLoaded('ticketItems')),
        ];
    }
}
