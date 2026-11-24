<?php

namespace App\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Models\Concerns\HasMoneyColumns;
use App\Models\User;
use Database\Factories\Domains\Invoicing\Models\CreditNoteRefundFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property PaymentMethod $payment_method
 * @property string $amount
 * @property int $amount_cents
 */
class CreditNoteRefund extends Model
{
    /** @use HasFactory<CreditNoteRefundFactory> */
    use HasFactory, HasMoneyColumns;

    protected $fillable = [
        'credit_note_id',
        'amount',
        'amount_cents',
        'refund_date',
        'payment_method',
        'reference',
        'notes',
        'created_by',
    ];

    protected $hidden = ['amount_cents'];

    protected $appends = ['amount', 'payment_method_label'];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'refund_date' => 'date',
            'payment_method' => PaymentMethod::class,
        ];
    }

    /** @return Attribute<string|null, array<string, int|null>> */
    protected function amount(): Attribute
    {
        return $this->moneyAttribute('amount');
    }

    /**
     * @return BelongsTo<CreditNote, $this>
     */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return $this->payment_method->label();
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Credit note refunds cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Credit note refunds cannot be deleted.');
        });
    }
}
