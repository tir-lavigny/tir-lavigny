<?php

namespace App\Models;

use App\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'total',
        'payment_method',
        'operating_day_id',
        'member_id',
    ];

    protected $casts = [
        'total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::deleted(function (Ticket $ticket) {
            $ticket->ticketItems()->delete();
        });

        static::restored(function (Ticket $ticket) {
            $ticket->ticketItems()->restore();
        });
    }

    public function operatingDay(): BelongsTo
    {
        return $this->belongsTo(OperatingDay::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function ticketItems(): HasMany
    {
        return $this->hasMany(TicketItem::class);
    }
}
