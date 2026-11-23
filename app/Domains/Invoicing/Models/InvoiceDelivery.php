<?php

namespace App\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\DeliveryStatus;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Models\User;
use Database\Factories\Domains\Invoicing\Models\InvoiceDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceDelivery extends Model
{
    /** @use HasFactory<InvoiceDeliveryFactory> */
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'channel',
        'recipient',
        'status',
        'processed_at',
        'processed_by',
        'notes',
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
