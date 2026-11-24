<?php

namespace App\Domains\Invoicing\Models;

use App\Models\User;
use Database\Factories\Domains\Invoicing\Models\InvoicingSettingsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'updated_by',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
