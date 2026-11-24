<?php

namespace App\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\Currency;
use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Models\Concerns\HasMoneyColumns;
use App\Models\User;
use Database\Factories\Domains\Invoicing\Models\PaymentFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string $amount
 * @property int $amount_cents
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasMoneyColumns;

    protected $fillable = [
        'amount',
        'amount_cents',
        'currency',
        'method',
        'payment_date',
        'reference',
        'notes',
        'created_by',
    ];

    protected $hidden = ['amount_cents'];

    protected $appends = ['amount'];

    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'method' => PaymentMethod::class,
            'payment_date' => 'date',
            'amount_cents' => 'integer',
        ];
    }

    /** @return Attribute<string|null, array<string, int|null>> */
    protected function amount(): Attribute
    {
        return $this->moneyAttribute('amount');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsToMany<Invoice, $this>
     */
    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'invoice_payments')
            ->withPivot('amount_cents')
            ->withTimestamps();
    }
}
