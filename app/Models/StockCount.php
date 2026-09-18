<?php

namespace App\Models;

use App\Enums\StockCountType;
use App\LogsActivity;
use Database\Factories\StockCountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCount extends Model
{
    /** @use HasFactory<StockCountFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'season_id',
        'stock_location_id',
        'type',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'type' => StockCountType::class,
        'completed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * @return BelongsTo<StockLocation, $this>
     */
    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class);
    }

    /**
     * @return HasMany<StockCountLine, $this>
     */
    public function stockCountLines(): HasMany
    {
        return $this->hasMany(StockCountLine::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany<StockMovement, $this>
     */
    public function stockMovements(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }
}