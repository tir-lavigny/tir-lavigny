<?php

namespace App\Models;

use App\LogsActivity;
use Database\Factories\StockTransferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use LogicException;

class StockTransfer extends Model
{
    /** @use HasFactory<StockTransferFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'from_location_id',
        'to_location_id',
        'season_id',
        'reason',
    ];

    protected $casts = [];

    /**
     * @return BelongsTo<StockLocation, $this>
     */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'from_location_id');
    }

    /**
     * @return BelongsTo<StockLocation, $this>
     */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'to_location_id');
    }

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * @return HasMany<StockTransferLine, $this>
     */
    public function stockTransferLines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class);
    }

    /**
     * @return MorphMany<StockMovement, $this>
     */
    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    protected static function booted(): void
    {
        static::deleting(function (StockTransfer $transfer) {
            throw new LogicException('Stock transfers cannot be deleted.');
        });
    }
}
