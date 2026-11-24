<?php

namespace App\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\CustomerAccountEntryType;
use App\Models\User;
use Database\Factories\Domains\Invoicing\Models\CustomerAccountEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property CustomerAccountEntryType $type
 */
class CustomerAccountEntry extends Model
{
    /** @use HasFactory<CustomerAccountEntryFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'billing_customer_id',
        'invoice_id',
        'type',
        'debit_cents',
        'credit_cents',
        'occurred_at',
        'created_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => CustomerAccountEntryType::class,
            'debit_cents' => 'integer',
            'credit_cents' => 'integer',
            'occurred_at' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BillingCustomer, $this>
     */
    public function billingCustomer(): BelongsTo
    {
        return $this->belongsTo(BillingCustomer::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Customer account entries cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Customer account entries cannot be deleted.');
        });
    }
}
