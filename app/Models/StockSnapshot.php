<?php

namespace App\Models;

use App\LogsActivity;
use Database\Factories\StockSnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class StockSnapshot extends Model
{
    /** @use HasFactory<StockSnapshotFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'stock_location_id',
        'snapshot_at',
    ];

    protected $casts = [
        'snapshot_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<StockLocation, $this>
     */
    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class);
    }

    /**
     * @return HasMany<StockSnapshotLine, $this>
     */
    public function stockSnapshotLines(): HasMany
    {
        return $this->hasMany(StockSnapshotLine::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (StockSnapshot $snapshot) {
            throw new LogicException('Stock snapshots cannot be deleted.');
        });
    }
}
