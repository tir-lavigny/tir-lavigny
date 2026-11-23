<?php

namespace App\Models;

use App\Models\Concerns\HasMoneyColumns;
use Database\Factories\PriceListItemFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $unit_price
 * @property int|null $unit_price_cents
 */
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

    protected $hidden = ['unit_price_cents'];

    protected $appends = ['unit_price'];

    protected $casts = [
        'unit_price_cents' => 'integer',
    ];

    /** @return Attribute<string|null, array<string, int|null>> */
    protected function unitPrice(): Attribute
    {
        return $this->moneyAttribute('unit_price');
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
