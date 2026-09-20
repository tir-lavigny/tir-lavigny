<?php

namespace App\Models;

use App\LogsActivity;
use Database\Factories\StockArchivedMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class StockArchivedMovement extends Model
{
    /** @use HasFactory<StockArchivedMovementFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'stock_location_id',
        'product_id',
        'season_id',
        'quantity',
        'type',
        'reference_type',
        'reference_id',
        'reason',
        'original_created_at',
        'original_updated_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'original_created_at' => 'datetime',
        'original_updated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<StockLocation, $this>
     */
    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (StockArchivedMovement $movement) {
            throw new LogicException('Archived stock movements cannot be deleted.');
        });
    }
}
