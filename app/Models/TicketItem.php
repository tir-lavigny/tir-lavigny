<?php

namespace App\Models;

use App\Models\Concerns\HasMoneyColumns;
use Database\Factories\TicketItemFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * @property string|null $unit_price
 * @property string|null $subtotal
 * @property int|null $unit_price_cents
 * @property int|null $subtotal_cents
 */
class TicketItem extends Model
{
    /** @use HasFactory<TicketItemFactory> */
    use HasFactory, HasMoneyColumns, SoftDeletes;

    protected $fillable = [
        'product_id',
        'ticket_id',
        'voucher_id',
        'quantity',
        'unit_price',
        'unit_price_cents',
        'subtotal',
        'subtotal_cents',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /** @return Attribute<string|null, array<string, int|string|null>> */
    protected function unitPrice(): Attribute
    {
        return $this->legacyMoneyAttribute('unit_price');
    }

    /** @return Attribute<int|null, array<string, int|string|null>> */
    protected function unitPriceCents(): Attribute
    {
        return $this->centsMoneyAttribute('unit_price');
    }

    /** @return Attribute<string|null, array<string, int|string|null>> */
    protected function subtotal(): Attribute
    {
        return $this->legacyMoneyAttribute('subtotal');
    }

    /** @return Attribute<int|null, array<string, int|string|null>> */
    protected function subtotalCents(): Attribute
    {
        return $this->centsMoneyAttribute('subtotal');
    }

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
