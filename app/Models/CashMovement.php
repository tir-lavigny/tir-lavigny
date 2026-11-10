<?php

namespace App\Models;

use App\LogsActivity;
use Database\Factories\CashMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    /** @use HasFactory<CashMovementFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'season_id',
        'amount',
        'occurred_at',
        'remark',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'occurred_at' => 'date',
    ];

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function isWithdrawal(): bool
    {
        return (float) $this->amount < 0;
    }
}
