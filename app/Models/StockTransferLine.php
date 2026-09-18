<?php

namespace App\Models;

use App\LogsActivity;
use Database\Factories\StockTransferLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class StockTransferLine extends Model
{
    /** @use HasFactory<StockTransferLineFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'stock_transfer_id',
        'product_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * @return BelongsTo<StockTransfer, $this>
     */
    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (StockTransferLine $line) {
            throw new LogicException('Stock transfer lines cannot be deleted.');
        });
    }
}