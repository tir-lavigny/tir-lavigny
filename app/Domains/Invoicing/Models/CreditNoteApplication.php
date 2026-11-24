<?php

namespace App\Domains\Invoicing\Models;

use App\Models\Concerns\HasMoneyColumns;
use App\Models\User;
use Database\Factories\Domains\Invoicing\Models\CreditNoteApplicationFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property string $amount
 * @property int $amount_cents
 */
class CreditNoteApplication extends Model
{
    /** @use HasFactory<CreditNoteApplicationFactory> */
    use HasFactory, HasMoneyColumns;

    protected $fillable = [
        'credit_note_id',
        'invoice_id',
        'amount',
        'amount_cents',
        'applied_at',
        'created_by',
    ];

    protected $hidden = ['amount_cents'];

    protected $appends = ['amount'];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'applied_at' => 'datetime',
        ];
    }

    /** @return Attribute<string|null, array<string, int|null>> */
    protected function amount(): Attribute
    {
        return $this->moneyAttribute('amount');
    }

    /**
     * @return BelongsTo<CreditNote, $this>
     */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
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

    /**
     * @return HasMany<CreditNoteApplicationReversal, $this>
     */
    public function reversals(): HasMany
    {
        return $this->hasMany(CreditNoteApplicationReversal::class, 'credit_note_application_id');
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Credit note applications cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Credit note applications cannot be deleted.');
        });
    }
}
