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
        'sold_at',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'sold_at' => 'datetime',
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

    /**
     * @return BelongsTo<OperatingDay, $this>
     */
    public function operatingDay(): BelongsTo
    {
        return $this->belongsTo(OperatingDay::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return HasMany<TicketItem, $this>
     */
    public function ticketItems(): HasMany
    {
        return $this->hasMany(TicketItem::class);
    }
}
