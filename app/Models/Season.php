<?php

namespace App\Models;

use App\LogsActivity;
use App\Models\Concerns\HasMoneyColumns;
use App\Support\Money;
use Database\Factories\SeasonFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string|null $initial_balance
 * @property int|null $initial_balance_cents
 * @property-read float|null $end_balance
 */
class Season extends Model
{
    /** @use HasFactory<SeasonFactory> */
    use HasFactory, HasMoneyColumns, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'initial_balance',
        'initial_balance_cents',
        'end_balance',
    ];

    /**
     * Cents columns stay internal: the serialized payload keeps exposing the
     * decimal attributes so API and Inertia consumers are unaffected.
     */
    protected $hidden = ['initial_balance_cents', 'end_balance_cents'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'initial_balance_cents' => 'integer',
    ];

    /** @return Attribute<string|null, array<string, int|string|null>> */
    protected function initialBalance(): Attribute
    {
        return $this->legacyMoneyAttribute('initial_balance');
    }

    /** @return Attribute<int|null, array<string, int|string|null>> */
    protected function initialBalanceCents(): Attribute
    {
        return $this->centsMoneyAttribute('initial_balance');
    }

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
        return Money::toFloat($this->closingBalanceCentsFor($day));
    }

    /**
     * Closing balance of an operating day in integer cents, from the ledger.
     */
    public function closingBalanceCentsFor(OperatingDay $day): int
    {
        return $this->closingBalanceLedger()['day_balances_cents'][$day->id]
            ?? (int) ($this->initial_balance_cents ?? 0);
    }

    /**
     * @return array{initial_balance: float, sales_total: float, movements_total: float, last_day_balance: float, post_last_day_movements: float, end_balance: float}
     */
    public function balanceSummary(): array
    {
        $ledger = $this->closingBalanceLedger();
        $dayBalancesCents = $ledger['day_balances_cents'];
        $lastDayBalanceCents = $dayBalancesCents === [] ? (int) ($this->initial_balance_cents ?? 0) : (int) end($dayBalancesCents);
        $salesTotalCents = (int) $this->operatingDays()->sum('total_cash_cents');
        $movementsTotalCents = (int) $this->cashMovements()->sum('amount_cents');

        return [
            'initial_balance' => Money::toFloat($this->initial_balance_cents ?? 0),
            'sales_total' => Money::toFloat($salesTotalCents),
            'movements_total' => Money::toFloat($movementsTotalCents),
            'last_day_balance' => Money::toFloat($lastDayBalanceCents),
            'post_last_day_movements' => Money::toFloat($ledger['end_balance_cents'] - $lastDayBalanceCents),
            'end_balance' => $ledger['end_balance'],
        ];
    }

    public function recalculateClosingBalances(): void
    {
        foreach ($this->closingBalanceLedger()['day_balances_cents'] as $dayId => $balanceCents) {
            OperatingDay::query()
                ->whereKey($dayId)
                ->whereNotNull('closed_at')
                ->update([
                    'closing_balance_cents' => $balanceCents,
                    'closing_balance' => Money::fromCents($balanceCents),
                ]);
        }
    }

    /**
     * Running cash balance of the register for every operating day of the season.
     *
     * A cash movement dated D counts from the first operating day dated on or
     * after D. Movements dated after the last operating day only affect the
     * end balance.
     *
     * @return array{day_balances: array<int, float>, day_balances_cents: array<int, int>, end_balance: float, end_balance_cents: int}
     */
    public function closingBalanceLedger(): array
    {
        $days = $this->operatingDays()
            ->orderBy('date')
            ->orderBy('id')
            ->get(['id', 'date', 'total_cash_cents']);

        $movements = $this->cashMovements()
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get(['amount_cents', 'occurred_at']);

        $runningCents = (int) ($this->initial_balance_cents ?? 0);
        $balancesCents = [];
        $index = 0;
        $movementCount = $movements->count();

        foreach ($days as $day) {
            $dayMovementsCents = 0;

            while ($index < $movementCount && $movements[$index]->occurred_at->lte($day->date)) {
                $dayMovementsCents += (int) $movements[$index]->amount_cents;
                $index++;
            }

            $runningCents += (int) $day->total_cash_cents + $dayMovementsCents;
            $balancesCents[$day->id] = $runningCents;
        }

        for (; $index < $movementCount; $index++) {
            $runningCents += (int) $movements[$index]->amount_cents;
        }

        return [
            'day_balances' => array_map(fn (int $cents): float => Money::toFloat($cents), $balancesCents),
            'day_balances_cents' => $balancesCents,
            'end_balance' => Money::toFloat($runningCents),
            'end_balance_cents' => $runningCents,
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
