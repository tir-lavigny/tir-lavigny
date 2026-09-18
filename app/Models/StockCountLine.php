<?php

namespace App\Models;

use App\LogsActivity;
use Database\Factories\StockCountLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockCountLine extends Model
{
    /** @use HasFactory<StockCountLineFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'stock_count_id',
        'product_id',
        'expected_quantity',
        'counted_quantity',
    ];

    protected $casts = [
        'expected_quantity' => 'integer',
        'counted_quantity' => 'integer',
    ];

    /**
     * @return BelongsTo<StockCount, $this>
     */
    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}