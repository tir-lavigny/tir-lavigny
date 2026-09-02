<?php

namespace App\Http\Resources;

use App\Models\OperatingDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read OperatingDay $resource
 */
class OperatingDayResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date,
            'started_at' => $this->started_at,
            'closed_at' => $this->closed_at,
            'season_id' => $this->season_id,
            'cash_remark' => $this->cash_remark,
            'closing_balance' => $this->closing_balance,
            'total_cash' => $this->total_cash,
            'total_twint' => $this->total_twint,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'tickets' => TicketResource::collection($this->whenLoaded('tickets')),
        ];
    }
}
