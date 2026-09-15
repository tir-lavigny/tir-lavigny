<?php

namespace App\Http\Resources;

use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Voucher $resource
 */
class VoucherResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'discount_percent' => $this->discount_percent,
            'discount_fixed' => $this->discount_fixed,
            'product_id' => $this->product_id,
            'season_id' => $this->season_id,
            'member_id' => $this->member_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
