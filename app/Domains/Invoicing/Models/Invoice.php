<?php

namespace App\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\Currency;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\InvoiceType;
use App\Domains\Invoicing\Policies\InvoicePolicy;
use App\Models\Concerns\HasMoneyColumns;
use App\Models\User;
use Database\Factories\Domains\Invoicing\Models\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property InvoiceStatus $status
 * @property InvoiceType $type
 * @property InvoiceDeliveryMethod $delivery_method
 * @property Currency $currency
 * @property Carbon|null $issue_date
 * @property Carbon|null $due_date
 * @property string $subtotal
 * @property string $total
 * @property int $subtotal_cents
 * @property int $total_cents
 */
#[UsePolicy(InvoicePolicy::class)]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, HasMoneyColumns;

    protected $fillable = [
        'billing_customer_id',
        'membership_fee_campaign_id',
        'type',
        'status',
        'invoice_number',
        'recipient_name',
        'recipient_address',
        'recipient_postal_code',
        'recipient_city',
        'recipient_country',
        'recipient_email',
        'delivery_method',
        'issue_date',
        'due_date',
        'currency',
        'subtotal',
        'subtotal_cents',
        'total',
        'total_cents',
        'issued_at',
        'sent_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'payment_reference',
        'creditor_name',
        'creditor_address',
        'creditor_postal_code',
        'creditor_city',
        'creditor_country',
        'creditor_iban',
        'created_by',
    ];

    protected $hidden = ['subtotal_cents', 'total_cents'];

    protected $appends = ['subtotal', 'total'];

    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'status' => InvoiceStatus::class,
            'delivery_method' => InvoiceDeliveryMethod::class,
            'currency' => Currency::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal_cents' => 'integer',
            'total_cents' => 'integer',
            'issued_at' => 'datetime',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
     * @return BelongsTo<BillingCustomer, $this>
     */
    public function billingCustomer(): BelongsTo
    {
        return $this->belongsTo(BillingCustomer::class);
    }

    /**
     * @return BelongsTo<MembershipFeeCampaign, $this>
     */
    public function membershipFeeCampaign(): BelongsTo
    {
        return $this->belongsTo(MembershipFeeCampaign::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    /**
     * @return MorphOne<FinancialDocument, $this>
     */
    public function document(): MorphOne
    {
        return $this->morphOne(FinancialDocument::class, 'documentable');
    }

    /**
     * @return HasMany<InvoiceDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(InvoiceDelivery::class)->latest('created_at')->latest('id');
    }

    /**
     * @return HasOne<InvoiceDelivery, $this>
     */
    public function latestDelivery(): HasOne
    {
        return $this->hasOne(InvoiceDelivery::class)->latestOfMany();
    }

    /**
     * @return HasMany<InvoiceReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(InvoiceReminder::class);
    }

    /**
     * @return HasMany<InvoiceActivityLog, $this>
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(InvoiceActivityLog::class);
    }

    /**
     * @return HasMany<InvoicePayment, $this>
     */
    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    /**
     * @return HasMany<CreditNote, $this>
     */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    /**
     * @return HasMany<CreditNoteApplication, $this>
     */
    public function creditNoteApplications(): HasMany
    {
        return $this->hasMany(CreditNoteApplication::class);
    }

    /**
     * @return HasManyThrough<CreditNoteApplicationReversal, CreditNoteApplication, $this>
     */
    public function creditNoteApplicationReversals(): HasManyThrough
    {
        return $this->hasManyThrough(
            CreditNoteApplicationReversal::class,
            CreditNoteApplication::class,
            'invoice_id',
            'credit_note_application_id',
        );
    }

    /**
     * @return BelongsToMany<Payment, $this>
     */
    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'invoice_payments')
            ->withPivot('amount_cents')
            ->withTimestamps();
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    public function isIssued(): bool
    {
        return $this->status === InvoiceStatus::Issued;
    }

    public function isCancelled(): bool
    {
        return $this->status === InvoiceStatus::Cancelled;
    }

    public function isCancellable(): bool
    {
        return $this->status === InvoiceStatus::Issued;
    }

    public function isDistributable(): bool
    {
        return in_array($this->status, [
            InvoiceStatus::Issued,
            InvoiceStatus::PartiallyPaid,
            InvoiceStatus::Paid,
        ], true);
    }

    protected static function booted(): void
    {
        static::updating(function (Invoice $invoice): void {
            $originalStatus = $invoice->getOriginal('status');
            $originalStatus = $originalStatus instanceof InvoiceStatus
                ? $originalStatus
                : InvoiceStatus::from((string) $originalStatus);

            if ($originalStatus === InvoiceStatus::Draft) {
                return;
            }

            $allowed = [
                'status',
                'issued_at',
                'sent_at',
                'delivery_method',
                'cancelled_at',
                'cancelled_by',
                'cancellation_reason',
                'payment_reference',
            ];

            if (array_diff(array_keys($invoice->getDirty()), $allowed) !== []) {
                throw new LogicException('Issued invoices cannot be modified.');
            }
        });

        static::deleting(function (Invoice $invoice): void {
            if ($invoice->status !== InvoiceStatus::Draft) {
                throw new LogicException('Only draft invoices can be deleted.');
            }
        });
    }
}
