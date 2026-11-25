<?php

namespace App\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\EnvelopeWindow;
use App\Models\User;
use Database\Factories\Domains\Invoicing\Models\InvoicingSettingsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property EnvelopeWindow $envelope_window
 * @property string|null $reply_to_email
 */
class InvoicingSettings extends Model
{
    /** @use HasFactory<InvoicingSettingsFactory> */
    use HasFactory;

    protected $fillable = [
        'creditor_name',
        'creditor_address',
        'creditor_postal_code',
        'creditor_city',
        'creditor_country',
        'creditor_iban',
        'reply_to_email',
        'envelope_window',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'envelope_window' => EnvelopeWindow::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
