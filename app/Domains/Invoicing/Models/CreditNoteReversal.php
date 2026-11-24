<?php

namespace App\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\CreditNoteReversalStatus;
use App\Domains\Invoicing\Enums\Currency;
use App\Models\Concerns\HasMoneyColumns;
use App\Models\User;
use Database\Factories\Domains\Invoicing\Models\CreditNoteReversalFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property CreditNoteReversalStatus $status
 * @property string $total
 * @property int $total_cents
 * @property Carbon|null $issue_date
 */
class CreditNoteReversal extends Model
{
    /** @use HasFactory<CreditNoteReversalFactory> */
    use HasFactory, HasMoneyColumns;

    protected $fillable = [
        'credit_note_id',
        'credit_note_reversal_number',
        'status',
        'reason',
        'issue_date',
        'currency',
        'total',
        'total_cents',
        'recipient_name',
        'recipient_address',
        'recipient_postal_code',
        'recipient_city',
        'recipient_country',
        'recipient_email',
        'creditor_name',
        'creditor_address',
        'creditor_postal_code',
        'creditor_city',
        'creditor_country',
        'creditor_iban',
        'created_by',
        'issued_at',
    ];

    protected $hidden = ['total_cents'];

    protected $appends = ['total'];

    protected function casts(): array
    {
        return [
            'status' => CreditNoteReversalStatus::class,
            'currency' => Currency::class,
            'issue_date' => 'date',
            'total_cents' => 'integer',
            'issued_at' => 'datetime',
        ];
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
     * @return HasMany<CreditNoteReversalItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteReversalItem::class)->orderBy('position');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === CreditNoteReversalStatus::Draft;
    }

    protected static function booted(): void
    {
        static::updating(function (CreditNoteReversal $reversal): void {
            $originalStatus = $reversal->getOriginal('status');
            $originalStatus = $originalStatus instanceof CreditNoteReversalStatus
                ? $originalStatus
                : CreditNoteReversalStatus::from((string) $originalStatus);

            if ($originalStatus === CreditNoteReversalStatus::Draft) {
                return;
            }

            if (array_diff(array_keys($reversal->getDirty()), ['status']) !== []) {
                throw new LogicException('Issued credit note reversals cannot be modified.');
            }
        });

        static::deleting(function (CreditNoteReversal $reversal): void {
            if (! $reversal->isDraft()) {
                throw new LogicException('Only draft credit note reversals can be deleted.');
            }
        });
    }
}
