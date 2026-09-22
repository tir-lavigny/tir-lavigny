<?php

namespace App\Models;

use App\LogsActivity;
use Database\Factories\SeasonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Season extends Model
{
    /** @use HasFactory<SeasonFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'initial_balance',
        'end_balance',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'initial_balance' => 'decimal:2',
        'end_balance' => 'decimal:2',
    ];

    /**
     * @return HasMany<OperatingDay, $this>
     */
    public function operatingDays(): HasMany
    {
        return $this->hasMany(OperatingDay::class);
    }

    /**
     * @return HasMany<Voucher, $this>
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

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
    public function stockTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class);
    }

    public function getEndBalanceAttribute(): ?float
    {
        return $this->initial_balance + $this->operatingDays()->sum('total_cash');
    }

    protected static function booted(): void
    {
        static::deleting(function (Season $season) {
            if ($season->operatingDays()->exists()) {
                abort(422, 'Impossible de supprimer cette saison car elle contient des journées.');
            }
            if ($season->vouchers()->exists()) {
                abort(422, 'Impossible de supprimer cette saison car elle contient des bons.');
            }
        });
    }
}
