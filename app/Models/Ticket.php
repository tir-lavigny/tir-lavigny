<?php

namespace App\Models;

use App\LogsActivity;
use App\Models\Concerns\HasMoneyColumns;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * @property string|null $total
 * @property int|null $total_cents
 */
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory, HasMoneyColumns, LogsActivity, SoftDeletes;

    protected $fillable = [
        'total',
        'total_cents',
        'payment_method',
        'operating_day_id',
        'member_id',
        'sold_at',
        'stock_location_id',
    ];

    /**
     * Cents columns stay internal: the serialized payload keeps exposing the
     * decimal attributes so API and Inertia consumers are unaffected.
     */
    protected $hidden = ['total_cents'];

    protected $casts = [
        'sold_at' => 'datetime',
        'total_cents' => 'integer',
    ];

    /** @return Attribute<string|null, array<string, int|string|null>> */
    protected function total(): Attribute
    {
        return $this->legacyMoneyAttribute('total');
    }

    /** @return Attribute<int|null, array<string, int|string|null>> */
    protected function totalCents(): Attribute
    {
        return $this->centsMoneyAttribute('total');
    }

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
        return $this->belongsTo(Member::class)->withTrashed();
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
