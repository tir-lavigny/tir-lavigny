<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\LogsActivity;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

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
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * @return MorphTo
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}