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
 * @property string $total_cash
 * @property string $total_twint
 * @property int $total_cash_cents
 * @property int $total_twint_cents
 * @property int|null $closing_balance_cents
 * @property-read float $closing_balance
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

    protected $hidden = ['closing_balance_cents', 'total_cash_cents', 'total_twint_cents'];

    protected $appends = ['closing_balance', 'total_cash', 'total_twint'];

    /**
     * A new day starts with zero totals, matching the default the legacy
     * decimal columns used to carry.
     */
    protected $attributes = [
        'total_cash_cents' => 0,
        'total_twint_cents' => 0,
    ];

    protected $casts = [
        'date' => 'date',
        'started_at' => 'datetime',
        'closed_at' => 'datetime',
        'closing_balance_cents' => 'integer',
        'total_cash_cents' => 'integer',
        'total_twint_cents' => 'integer',
    ];

    /** @return Attribute<string|null, array<string, int|null>> */
    protected function totalCash(): Attribute
    {
        return $this->moneyAttribute('total_cash');
    }

    /** @return Attribute<string|null, array<string, int|null>> */
    protected function totalTwint(): Attribute
    {
        return $this->moneyAttribute('total_twint');
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

                return ($attributes['closed_at'] ?? null) === null
                    ? $this->season?->closingBalanceFor($this)
                    : null;
            },
            set: function (mixed $value): array {
                return ['closing_balance_cents' => Money::toCents($value)];
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
