<?php

namespace App\Models;

use App\LogsActivity;
use App\Models\Concerns\HasMoneyColumns;
use App\Support\Money;
use Database\Factories\OperatingDayFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string|null $total_cash
 * @property string|null $total_twint
 * @property int|null $total_cash_cents
 * @property int|null $total_twint_cents
 * @property int|null $closing_balance_cents
 * @property-read float|null $closing_balance
 */
class OperatingDay extends Model
{
    /** @use HasFactory<OperatingDayFactory> */
    use HasFactory, HasMoneyColumns, LogsActivity;

    protected $fillable = [
        'date',
        'started_at',
        'closed_at',
        'season_id',
        'cash_remark',
        'closing_balance',
        'closing_balance_cents',
        'total_cash',
        'total_cash_cents',
        'total_twint',
        'total_twint_cents',
    ];

    /**
     * Cents columns stay internal: the serialized payload keeps exposing the
     * decimal attributes so API and Inertia consumers are unaffected.
     */
    protected $hidden = ['closing_balance_cents', 'total_cash_cents', 'total_twint_cents'];

    protected $casts = [
        'date' => 'date',
        'started_at' => 'datetime',
        'closed_at' => 'datetime',
        'closing_balance_cents' => 'integer',
        'total_cash_cents' => 'integer',
        'total_twint_cents' => 'integer',
    ];

    /** @return Attribute<string|null, array<string, int|string|null>> */
    protected function totalCash(): Attribute
    {
        return $this->legacyMoneyAttribute('total_cash');
    }

    /** @return Attribute<int|null, array<string, int|string|null>> */
    protected function totalCashCents(): Attribute
    {
        return $this->centsMoneyAttribute('total_cash');
    }

    /** @return Attribute<string|null, array<string, int|string|null>> */
    protected function totalTwint(): Attribute
    {
        return $this->legacyMoneyAttribute('total_twint');
    }

    /** @return Attribute<int|null, array<string, int|string|null>> */
    protected function totalTwintCents(): Attribute
    {
        return $this->centsMoneyAttribute('total_twint');
    }

    /** @return Attribute<int|null, array<string, int|string|null>> */
    protected function closingBalanceCents(): Attribute
    {
        return $this->centsMoneyAttribute('closing_balance');
    }

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /** @return Attribute<float|null, array<string, int|string|null>> */
    protected function closingBalance(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes): ?float {
                $cents = $attributes['closing_balance_cents'] ?? null;

                if ($cents !== null) {
                    return Money::toFloat((int) $cents);
                }

                if (($attributes['closed_at'] ?? null) !== null && $value !== null) {
                    return Money::toFloat(Money::toCents($value));
                }

                return $this->season?->closingBalanceFor($this);
            },
            set: function (mixed $value): array {
                $cents = Money::toCents($value);

                return [
                    'closing_balance_cents' => $cents,
                    'closing_balance' => Money::fromCents($cents),
                ];
            },
        );
    }

    public function recalculateTotals(): void
    {
        $totals = $this->tickets()
            ->selectRaw('payment_method, sum(total_cents) as total_cents')
            ->groupBy('payment_method')
            ->pluck('total_cents', 'payment_method');

        $this->update([
            'total_cash_cents' => (int) ($totals->get('cash') ?? 0),
            'total_twint_cents' => (int) ($totals->get('twint') ?? 0),
        ]);
    }

    protected static function booted(): void
    {
        static::deleting(function (OperatingDay $day) {
            if ($day->tickets()->exists()) {
                abort(422, 'Impossible de supprimer cette journée car elle contient des tickets.');
            }
        });
    }
}
