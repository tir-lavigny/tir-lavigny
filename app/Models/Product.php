<?php

namespace App\Models;

use App\LogsActivity;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'picture',
        'unit_price',
        'allows_custom_price',
        'category_id',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'allows_custom_price' => 'boolean',
    ];

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
}
