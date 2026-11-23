<?php

namespace App\Models;

use App\Models\Concerns\HasMoneyColumns;
use Database\Factories\PriceListItemFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceListItem extends Model
{
    /** @use HasFactory<PriceListItemFactory> */
    use HasFactory, HasMoneyColumns;

    protected $fillable = [
        'price_list_id',
        'product_id',
        'unit_price',
        'unit_price_cents',
    ];

    protected function unitPrice(): Attribute
    {
        return $this->legacyMoneyAttribute('unit_price');
    }

    protected function unitPriceCents(): Attribute
    {
        return $this->centsMoneyAttribute('unit_price');
    }

    /**
     * @return BelongsTo<PriceList, $this>
     */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
