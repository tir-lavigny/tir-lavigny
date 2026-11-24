<?php

namespace App\Domains\Invoicing\Models;

use App\Models\Concerns\HasMoneyColumns;
use Database\Factories\Domains\Invoicing\Models\InvoicePaymentFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $amount
 * @property int $amount_cents
 */
class InvoicePayment extends Model
{
    /** @use HasFactory<InvoicePaymentFactory> */
    use HasFactory, HasMoneyColumns;

    protected $fillable = [
        'invoice_id',
        'payment_id',
        'amount',
        'amount_cents',
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
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
