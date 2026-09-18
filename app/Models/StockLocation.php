<?php

namespace App\Models;

use App\LogsActivity;
use Database\Factories\StockLocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockLocation extends Model
{
    /** @use HasFactory<StockLocationFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * @return HasMany<StockCount, $this>
     */
    public function stockCounts(): HasMany
    {
        return $this->hasMany(StockCount::class);
    }

    /**
     * @return HasMany<StockTransfer, $this>
     */
    public function fromTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'from_location_id');
    }

    /**
     * @return HasMany<StockTransfer, $this>
     */
    public function toTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'to_location_id');
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}