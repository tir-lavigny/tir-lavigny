<?php

namespace App\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\CreditNoteStatus;
use App\Models\Concerns\HasMoneyColumns;
use Database\Factories\Domains\Invoicing\Models\CreditNoteItemFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property string $unit_price
 * @property string $total
 * @property int $unit_price_cents
 * @property int $total_cents
 */
class CreditNoteItem extends Model
{
    /** @use HasFactory<CreditNoteItemFactory> */
    use HasFactory, HasMoneyColumns;

    protected $fillable = [
        'credit_note_id',
        'invoice_item_id',
        'description',
        'quantity',
        'unit_price',
        'unit_price_cents',
        'total',
        'total_cents',
        'position',
        'metadata',
    ];

    protected $hidden = ['unit_price_cents', 'total_cents'];

    protected $appends = ['unit_price', 'total'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price_cents' => 'integer',
            'total_cents' => 'integer',
            'metadata' => 'array',
        ];
    }

    /** @return Attribute<string|null, array<string, int|null>> */
    protected function unitPrice(): Attribute
    {
        return $this->moneyAttribute('unit_price');
    }

    /** @return Attribute<string|null, array<string, int|null>> */
    protected function total(): Attribute
    {
        return $this->moneyAttribute('total');
    }

    /**
     * @return BelongsTo<CreditNote, $this>
     */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    /**
     * @return BelongsTo<InvoiceItem, $this>
     */
    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    protected static function booted(): void
    {
        static::saving(function (CreditNoteItem $item): void {
            $status = $item->creditNote()->first()?->status;

            if ($status !== null && $status !== CreditNoteStatus::Draft) {
                throw new LogicException('Issued credit note lines cannot be modified.');
            }
        });

        static::deleting(function (CreditNoteItem $item): void {
            $status = $item->creditNote()->first()?->status;

            if ($status !== null && $status !== CreditNoteStatus::Draft) {
                throw new LogicException('Issued credit note lines cannot be deleted.');
            }
        });
    }
}
