<?php

namespace App\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\DeliveryStatus;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Models\User;
use Database\Factories\Domains\Invoicing\Models\InvoiceDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property InvoiceDeliveryMethod $channel
 * @property DeliveryStatus $status
 * @property Carbon|null $processed_at
 * @property string|null $batch_id
 * @property string|null $error
 * @property Invoice $invoice
 * @property User|null $processor
 */
class InvoiceDelivery extends Model
{
    /** @use HasFactory<InvoiceDeliveryFactory> */
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'channel',
        'recipient',
        'batch_id',
        'status',
        'processed_at',
        'processed_by',
        'notes',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'channel' => InvoiceDeliveryMethod::class,
            'status' => DeliveryStatus::class,
            'processed_at' => 'datetime',
        ];
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
