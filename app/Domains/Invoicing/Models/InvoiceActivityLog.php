<?php

namespace App\Domains\Invoicing\Models;

use App\Models\User;
use Database\Factories\Domains\Invoicing\Models\InvoiceActivityLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InvoiceActivityLog extends Model
{
    /** @use HasFactory<InvoiceActivityLogFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'invoice_id',
        'user_id',
        'action',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Invoice activity logs cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Invoice activity logs cannot be deleted.');
        });
    }
}
