<?php

namespace App\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\BillingCustomerType;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Policies\BillingCustomerPolicy;
use App\Models\Member;
use Database\Factories\Domains\Invoicing\Models\BillingCustomerFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property BillingCustomerType $type
 * @property InvoiceDeliveryMethod $preferred_invoice_delivery
 */
#[UsePolicy(BillingCustomerPolicy::class)]
class BillingCustomer extends Model
{
    /** @use HasFactory<BillingCustomerFactory> */
    use HasFactory;

    protected $fillable = [
        'member_id',
        'type',
        'first_name',
        'last_name',
        'organization_name',
        'address',
        'postal_code',
        'city',
        'country',
        'email',
        'phone',
        'preferred_invoice_delivery',
    ];

    protected function casts(): array
    {
        return [
            'type' => BillingCustomerType::class,
            'preferred_invoice_delivery' => InvoiceDeliveryMethod::class,
        ];
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<MembershipFeeCampaignMember, $this>
     */
    public function campaignMembers(): HasMany
    {
        return $this->hasMany(MembershipFeeCampaignMember::class);
    }

    public function displayName(): string
    {
        if ($this->type === BillingCustomerType::Organization) {
            return (string) $this->organization_name;
        }

        return trim($this->first_name.' '.$this->last_name);
    }
}
