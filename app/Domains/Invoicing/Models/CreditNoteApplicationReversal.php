<?php

namespace App\Domains\Invoicing\Models;

use App\Models\Concerns\HasMoneyColumns;
use App\Models\User;
use Database\Factories\Domains\Invoicing\Models\CreditNoteApplicationReversalFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property string $amount
 * @property int $amount_cents
 */
class CreditNoteApplicationReversal extends Model
{
    /** @use HasFactory<CreditNoteApplicationReversalFactory> */
    use HasFactory, HasMoneyColumns;

    public $timestamps = false;

    protected $fillable = [
        'credit_note_application_id',
        'amount',
        'amount_cents',
        'reason',
        'reversed_at',
        'created_by',
    ];

    protected $hidden = ['amount_cents'];

    protected $appends = ['amount'];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'reversed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @return Attribute<string|null, array<string, int|null>> */
    protected function amount(): Attribute
    {
        return $this->moneyAttribute('amount');
    }

    /**
     * @return BelongsTo<CreditNoteApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(CreditNoteApplication::class, 'credit_note_application_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Credit application reversals cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Credit application reversals cannot be deleted.');
        });
    }
}
