<?php

namespace App\Models;

use Database\Factories\TicketItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

class TicketItem extends Model
{
    /** @use HasFactory<TicketItemFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'ticket_id',
        'voucher_id',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::deleting(function (TicketItem $ticketItem) {
            if (! $ticketItem->isForceDeleting()) {
                return;
            }

            throw new LogicException(
                'Ticket items cannot be permanently deleted.'
            );
        });

        static::restoring(function (TicketItem $ticketItem) {
            throw new LogicException(
                'Deleted ticket items cannot be restored.'
            );
        });
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<Voucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class)->withTrashed();
    }
}
