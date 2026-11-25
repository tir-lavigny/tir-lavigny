<?php

namespace App\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\Currency;
use App\Domains\Invoicing\Enums\PaymentMethod;
use App\LogsActivity;
use App\Models\Concerns\HasMoneyColumns;
use App\Models\User;
use Database\Factories\Domains\Invoicing\Models\PaymentFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $amount
 * @property int $amount_cents
 * @property Currency $currency
 * @property PaymentMethod $method
 * @property Carbon|null $payment_date
 * @property Carbon|null $voided_at
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasMoneyColumns, LogsActivity;

    protected $fillable = [
        'amount',
        'amount_cents',
        'currency',
        'method',
        'payment_date',
        'reference',
        'notes',
        'created_by',
        'idempotency_key',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected $hidden = ['amount_cents', 'idempotency_key'];

    protected $appends = ['amount', 'method_label'];

    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'method' => PaymentMethod::class,
            'payment_date' => 'date',
            'amount_cents' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    /** @return Attribute<string|null, array<string, int|null>> */
    protected function amount(): Attribute
    {
        return $this->moneyAttribute('amount');
    }

    public function getMethodLabelAttribute(): string
    {
        return $this->method->label();
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
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
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * @return HasMany<InvoicePayment, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->latest('created_at')->latest('id');
    }

    /**
     * @return BelongsToMany<Invoice, $this>
     */
    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'invoice_payments')
            ->wherePivotNull('reversed_at')
            ->withPivot(['amount_cents', 'reversed_at'])
            ->withTimestamps();
    }
}
