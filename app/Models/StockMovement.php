<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\LogsActivity;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
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
    ];

    protected $casts = [
        'quantity' => 'integer',
        'type' => StockMovementType::class,
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

    /**
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function booted(): void
    {
        static::deleting(function (StockMovement $movement) {
            throw new LogicException('Stock movements cannot be deleted.');
        });
    }
}