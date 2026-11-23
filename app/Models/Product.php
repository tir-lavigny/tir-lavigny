<?php

namespace App\Models;

use App\LogsActivity;
use App\Models\Concerns\HasMoneyColumns;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read Pivot|null $pivot
 * @property string|null $unit_price
 * @property int|null $unit_price_cents
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasMoneyColumns, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'picture',
        'unit_price',
        'unit_price_cents',
        'allows_custom_price',
        'category_id',
        'is_stockable',
    ];

    /**
     * Cents columns stay internal: the serialized payload keeps exposing the
     * decimal attributes so API and Inertia consumers are unaffected.
     */
    protected $hidden = ['unit_price_cents'];

    protected $casts = [
        'allows_custom_price' => 'boolean',
        'is_stockable' => 'boolean',
        'unit_price_cents' => 'integer',
    ];

    /** @return Attribute<string|null, array<string, int|string|null>> */
    protected function unitPrice(): Attribute
    {
        return $this->legacyMoneyAttribute('unit_price');
    }

    /** @return Attribute<int|null, array<string, int|string|null>> */
    protected function unitPriceCents(): Attribute
    {
        return $this->centsMoneyAttribute('unit_price');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function childProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_product', 'product_id', 'child_product_id')
            ->withPivot('quantity');
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function parentProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_product', 'child_product_id', 'product_id')
            ->withPivot('quantity');
    }

    /**
     * @return HasMany<TicketItem, $this>
     */
    public function ticketItems(): HasMany
    {
        return $this->hasMany(TicketItem::class);
    }

    /**
     * @return HasMany<Voucher, $this>
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * @return HasMany<StockCountLine, $this>
     */
    public function stockCountLines(): HasMany
    {
        return $this->hasMany(StockCountLine::class);
    }

    /**
     * @return HasMany<StockTransferLine, $this>
     */
    public function stockTransferLines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class);
    }

    /**
     * @return HasMany<PriceListItem, $this>
     */
    public function priceListItems(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }

    public function isCorrection(): bool
    {
        return $this->name === 'Correction';
    }

    protected static function booted(): void
    {
        static::updating(function (Product $product) {
            if ($product->getOriginal('name') === 'Correction' && $product->isDirty(['name', 'category_id', 'unit_price', 'allows_custom_price', 'is_stockable'])) {
                abort(422, 'Le produit Correction est protégé.');
            }
        });

        static::deleting(function (Product $product) {
            if ($product->isCorrection()) {
                abort(422, 'Le produit Correction est protégé.');
            }
        });
    }
}
