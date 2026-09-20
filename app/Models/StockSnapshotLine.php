<?php

namespace App\Models;

use App\LogsActivity;
use Database\Factories\StockSnapshotLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class StockSnapshotLine extends Model
{
    /** @use HasFactory<StockSnapshotLineFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'stock_snapshot_id',
        'product_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * @return BelongsTo<StockSnapshot, $this>
     */
    public function stockSnapshot(): BelongsTo
    {
        return $this->belongsTo(StockSnapshot::class);
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
        static::deleting(function (StockSnapshotLine $line) {
            throw new LogicException('Stock snapshot lines cannot be deleted.');
        });
    }
}
