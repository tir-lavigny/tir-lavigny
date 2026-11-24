<?php

namespace App\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\DeliveryStatus;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Models\Concerns\HasMoneyColumns;
use App\Models\User;
use Database\Factories\Domains\Invoicing\Models\InvoiceReminderFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $outstanding_amount
 * @property int $outstanding_amount_cents
 */
class InvoiceReminder extends Model
{
    /** @use HasFactory<InvoiceReminderFactory> */
    use HasFactory, HasMoneyColumns;

    protected $fillable = [
        'invoice_id',
        'channel',
        'recipient',
        'outstanding_amount',
        'outstanding_amount_cents',
        'status',
        'processed_at',
        'processed_by',
    ];

    protected $hidden = ['outstanding_amount_cents'];

    protected $appends = ['outstanding_amount'];

    protected function casts(): array
    {
        return [
            'channel' => InvoiceDeliveryMethod::class,
            'status' => DeliveryStatus::class,
            'outstanding_amount_cents' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    /** @return Attribute<string|null, array<string, int|null>> */
    protected function outstandingAmount(): Attribute
    {
        return $this->moneyAttribute('outstanding_amount');
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
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
