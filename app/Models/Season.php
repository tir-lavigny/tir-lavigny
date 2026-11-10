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

    /**
     * @return HasMany<CashMovement, $this>
     */
    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function getEndBalanceAttribute(): ?float
    {
        return $this->closingBalanceLedger()['end_balance'];
    }

    public function closingBalanceFor(OperatingDay $day): float
    {
        return $this->closingBalanceLedger()['day_balances'][$day->id]
            ?? (float) $this->initial_balance;
    }

    /**
     * @return array{initial_balance: float, sales_total: float, movements_total: float, last_day_balance: float, post_last_day_movements: float, end_balance: float}
     */
    public function balanceSummary(): array
    {
        $ledger = $this->closingBalanceLedger();
        $dayBalances = $ledger['day_balances'];
        $lastDayBalance = $dayBalances === [] ? (float) $this->initial_balance : (float) end($dayBalances);

        return [
            'initial_balance' => (float) $this->initial_balance,
            'sales_total' => round((float) $this->operatingDays()->sum('total_cash'), 2),
            'movements_total' => round((float) $this->cashMovements()->sum('amount'), 2),
            'last_day_balance' => $lastDayBalance,
            'post_last_day_movements' => round($ledger['end_balance'] - $lastDayBalance, 2),
            'end_balance' => $ledger['end_balance'],
        ];
    }

    public function recalculateClosingBalances(): void
    {
        foreach ($this->closingBalanceLedger()['day_balances'] as $dayId => $balance) {
            OperatingDay::query()
                ->whereKey($dayId)
                ->whereNotNull('closed_at')
                ->update(['closing_balance' => $balance]);
        }
    }

    /**
     * Running cash balance of the register for every operating day of the season.
     *
     * A cash movement dated D counts from the first operating day dated on or
     * after D. Movements dated after the last operating day only affect the
     * end balance.
     *
     * @return array{day_balances: array<int, float>, end_balance: float}
     */
    public function closingBalanceLedger(): array
    {
        $days = $this->operatingDays()
            ->orderBy('date')
            ->orderBy('id')
            ->get(['id', 'date', 'total_cash']);

        $movements = $this->cashMovements()
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get(['amount', 'occurred_at']);

        $running = (float) $this->initial_balance;
        $balances = [];
        $index = 0;
        $movementCount = $movements->count();

        foreach ($days as $day) {
            $dayMovements = 0.0;

            while ($index < $movementCount && $movements[$index]->occurred_at->lte($day->date)) {
                $dayMovements += (float) $movements[$index]->amount;
                $index++;
            }

            $running += (float) $day->total_cash + $dayMovements;
            $balances[$day->id] = round($running, 2);
        }

        for (; $index < $movementCount; $index++) {
            $running += (float) $movements[$index]->amount;
        }

        return [
            'day_balances' => $balances,
            'end_balance' => round($running, 2),
        ];
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
