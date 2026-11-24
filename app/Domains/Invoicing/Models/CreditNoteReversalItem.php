<?php

namespace App\Domains\Invoicing\Models;

use App\Models\Concerns\HasMoneyColumns;
use Database\Factories\Domains\Invoicing\Models\CreditNoteReversalItemFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property string $amount
 * @property int $amount_cents
 */
class CreditNoteReversalItem extends Model
{
    /** @use HasFactory<CreditNoteReversalItemFactory> */
    use HasFactory, HasMoneyColumns;

    protected $fillable = [
        'credit_note_reversal_id',
        'credit_note_item_id',
        'description',
        'amount',
        'amount_cents',
        'position',
    ];

    protected $hidden = ['amount_cents'];

    protected $appends = ['amount'];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
        ];
    }

    /** @return Attribute<string|null, array<string, int|null>> */
    protected function amount(): Attribute
    {
        return $this->moneyAttribute('amount');
    }

    /**
     * @return BelongsTo<CreditNoteReversal, $this>
     */
    public function reversal(): BelongsTo
    {
        return $this->belongsTo(CreditNoteReversal::class, 'credit_note_reversal_id');
    }

    /**
     * @return BelongsTo<CreditNoteItem, $this>
     */
    public function creditNoteItem(): BelongsTo
    {
        return $this->belongsTo(CreditNoteItem::class);
    }

    protected static function booted(): void
    {
        static::saving(function (CreditNoteReversalItem $item): void {
            $status = $item->reversal()->first()?->status;

            if ($status !== null && $status->value !== 'draft') {
                throw new LogicException('Issued credit note reversal lines cannot be modified.');
            }
        });

        static::deleting(function (CreditNoteReversalItem $item): void {
            $status = $item->reversal()->first()?->status;

            if ($status !== null && $status->value !== 'draft') {
                throw new LogicException('Issued credit note reversal lines cannot be deleted.');
            }
        });
    }
}
