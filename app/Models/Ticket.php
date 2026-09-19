<?php

namespace App\Models;

use App\LogsActivity;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'total',
        'payment_method',
        'operating_day_id',
        'member_id',
        'sold_at',
        'stock_location_id',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'sold_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Ticket $ticket) {
            if (! $ticket->isForceDeleting()) {
                return;
            }

            throw new LogicException(
                'Tickets cannot be permanently deleted.'
            );
        });

        static::deleted(function (Ticket $ticket) {
            $ticket->ticketItems()->delete();
        });

        static::restoring(function (Ticket $ticket) {
            throw new LogicException(
                'Deleted tickets cannot be restored because their stock movements are immutable.'
            );
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

    /**
     * @return BelongsTo<StockLocation, $this>
     */
    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class);
    }

    /**
     * @return MorphMany<StockMovement, $this>
     */
    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }
}
