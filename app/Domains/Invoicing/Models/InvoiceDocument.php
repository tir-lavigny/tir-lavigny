<?php

namespace App\Domains\Invoicing\Models;

use Database\Factories\Domains\Invoicing\Models\InvoiceDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceDocument extends Model
{
    /** @use HasFactory<InvoiceDocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'path',
        'sha256',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
