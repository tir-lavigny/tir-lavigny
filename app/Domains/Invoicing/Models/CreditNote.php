<?php

namespace App\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\CreditNoteStatus;
use App\Domains\Invoicing\Enums\Currency;
use App\Domains\Invoicing\Policies\CreditNotePolicy;
use App\Models\Concerns\HasMoneyColumns;
use App\Models\User;
use Database\Factories\Domains\Invoicing\Models\CreditNoteFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property CreditNoteStatus $status
 * @property string $subtotal
 * @property string $total
 * @property int $subtotal_cents
 * @property int $total_cents
 * @property Carbon|null $issue_date
 */
#[UsePolicy(CreditNotePolicy::class)]
class CreditNote extends Model
{
    /** @use HasFactory<CreditNoteFactory> */
    use HasFactory, HasMoneyColumns;

    protected $fillable = [
        'invoice_id',
        'billing_customer_id',
        'credit_note_number',
        'status',
        'reason',
        'issue_date',
        'currency',
        'subtotal',
        'subtotal_cents',
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

    protected $hidden = ['subtotal_cents', 'total_cents'];

    protected $appends = ['subtotal', 'total'];

    protected function casts(): array
    {
        return [
            'status' => CreditNoteStatus::class,
            'currency' => Currency::class,
            'issue_date' => 'date',
            'subtotal_cents' => 'integer',
            'total_cents' => 'integer',
            'issued_at' => 'datetime',
        ];
    }

    /** @return Attribute<string|null, array<string, int|null>> */
    protected function subtotal(): Attribute
    {
        return $this->moneyAttribute('subtotal');
    }

    /** @return Attribute<string|null, array<string, int|null>> */
    protected function total(): Attribute
    {
        return $this->moneyAttribute('total');
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<BillingCustomer, $this>
     */
    public function billingCustomer(): BelongsTo
    {
        return $this->belongsTo(BillingCustomer::class);
    }

    /**
     * @return HasMany<CreditNoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class)->orderBy('position');
    }

    /**
     * @return HasMany<CreditNoteApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(CreditNoteApplication::class);
    }

    /**
     * @return HasMany<CreditNoteRefund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(CreditNoteRefund::class);
    }

    /**
     * @return HasMany<CreditNoteReversal, $this>
     */
    public function reversals(): HasMany
    {
        return $this->hasMany(CreditNoteReversal::class);
    }

    /**
     * @return MorphOne<FinancialDocument, $this>
     */
    public function document(): MorphOne
    {
        return $this->morphOne(FinancialDocument::class, 'documentable');
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
        return $this->status === CreditNoteStatus::Draft;
    }

    public function isIssued(): bool
    {
        return $this->status !== CreditNoteStatus::Draft;
    }

    protected static function booted(): void
    {
        static::updating(function (CreditNote $creditNote): void {
            $originalStatus = $creditNote->getOriginal('status');
            $originalStatus = $originalStatus instanceof CreditNoteStatus
                ? $originalStatus
                : CreditNoteStatus::from((string) $originalStatus);

            if ($originalStatus === CreditNoteStatus::Draft) {
                return;
            }

            if (array_diff(array_keys($creditNote->getDirty()), ['status']) !== []) {
                throw new LogicException('Issued credit notes cannot be modified.');
            }
        });

        static::deleting(function (CreditNote $creditNote): void {
            if ($creditNote->status !== CreditNoteStatus::Draft) {
                throw new LogicException('Only draft credit notes can be deleted.');
            }
        });
    }
}
