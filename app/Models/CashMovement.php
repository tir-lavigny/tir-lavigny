<?php

namespace App\Models;

use App\LogsActivity;
use App\Models\Concerns\HasMoneyColumns;
use Database\Factories\CashMovementFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string|null $amount
 * @property int|null $amount_cents
 */
class CashMovement extends Model
{
    /** @use HasFactory<CashMovementFactory> */
    use HasFactory, HasMoneyColumns, LogsActivity;

    protected $fillable = [
        'season_id',
        'amount',
        'amount_cents',
        'occurred_at',
        'remark',
    ];

    /**
     * Cents columns stay internal: the serialized payload keeps exposing the
     * decimal attributes so API and Inertia consumers are unaffected.
     */
    protected $hidden = ['amount_cents'];

    protected $casts = [
        'occurred_at' => 'date',
        'amount_cents' => 'integer',
    ];

    /** @return Attribute<string|null, array<string, int|string|null>> */
    protected function amount(): Attribute
    {
        return $this->legacyMoneyAttribute('amount');
    }

    /** @return Attribute<int|null, array<string, int|string|null>> */
    protected function amountCents(): Attribute
    {
        return $this->centsMoneyAttribute('amount');
    }

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function isWithdrawal(): bool
    {
        return (int) $this->amount_cents < 0;
    }
}
