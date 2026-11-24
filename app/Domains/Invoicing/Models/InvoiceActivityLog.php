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

    protected $appends = ['action_label'];

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

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'invoice.created' => 'Facture créée',
            'invoice.updated' => 'Facture modifiée',
            'invoice.issued' => 'Facture émise',
            'invoice.cancelled' => 'Facture annulée',
            'invoice.deleted' => 'Brouillon supprimé',
            'credit_note.created' => 'Avoir créé',
            'credit_note.updated' => 'Avoir modifié',
            'credit_note.deleted' => 'Avoir supprimé',
            'credit_note.issued' => 'Avoir émis',
            'credit_note.refunded' => 'Remboursement enregistré',
            'credit_note.reversal_created' => "Avoir d'annulation créé",
            'credit_note.reversal_issued' => "Avoir d'annulation émis",
            default => $this->action,
        };
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
