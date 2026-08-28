<?php

namespace App\Models;

use App\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperatingDay extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'date',
        'started_at',
        'closed_at',
        'season_id',
        'cash_remark',
        'closing_balance',
        'total_cash',
        'total_twint',
    ];

    protected $casts = [
        'date' => 'date',
        'started_at' => 'datetime',
        'closed_at' => 'datetime',
        'closing_balance' => 'decimal:2',
        'total_cash' => 'decimal:2',
        'total_twint' => 'decimal:2',
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    public function recalculateTotals(): void
    {
        $totals = $this->tickets()
            ->selectRaw('payment_method, sum(total) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method');

        $this->update([
            'total_cash' => $totals->get('cash', 0),
            'total_twint' => $totals->get('twint', 0),
        ]);
    }
}
