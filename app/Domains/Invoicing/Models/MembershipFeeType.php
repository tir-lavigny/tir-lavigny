<?php

namespace App\Domains\Invoicing\Models;

use App\Models\Concerns\HasMoneyColumns;
use Database\Factories\Domains\Invoicing\Models\MembershipFeeTypeFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $amount
 * @property int $amount_cents
 */
class MembershipFeeType extends Model
{
    /** @use HasFactory<MembershipFeeTypeFactory> */
    use HasFactory, HasMoneyColumns;

    protected $fillable = [
        'name',
        'description',
        'amount',
        'amount_cents',
        'is_active',
    ];

    protected $hidden = ['amount_cents'];

    protected $appends = ['amount'];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return Attribute<string|null, array<string, int|null>> */
    protected function amount(): Attribute
    {
        return $this->moneyAttribute('amount');
    }
}
