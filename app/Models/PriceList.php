<?php

namespace App\Models;

use App\LogsActivity;
use Database\Factories\PriceListFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceList extends Model
{
    /** @use HasFactory<PriceListFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'notes',
        'is_active',
        'last_applied_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_applied_at' => 'datetime',
    ];

    /**
     * @return HasMany<PriceListItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }

    /**
     * @return array<int, float>
     */
    public function activeItemPrices(): array
    {
        return $this->items()
            ->pluck('unit_price', 'product_id')
            ->map(fn ($price): float => (float) $price)
            ->all();
    }
}
