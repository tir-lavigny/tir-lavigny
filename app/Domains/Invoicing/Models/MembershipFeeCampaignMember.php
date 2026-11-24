<?php

namespace App\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\CampaignMemberStatus;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Models\Concerns\HasMoneyColumns;
use App\Models\Member;
use App\Models\User;
use Database\Factories\Domains\Invoicing\Models\MembershipFeeCampaignMemberFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $base_amount
 * @property string $discount_amount
 * @property string $final_amount
 * @property int $base_amount_cents
 * @property int $discount_amount_cents
 * @property int $final_amount_cents
 */
class MembershipFeeCampaignMember extends Model
{
    /** @use HasFactory<MembershipFeeCampaignMemberFactory> */
    use HasFactory, HasMoneyColumns;

    protected $fillable = [
        'membership_fee_campaign_id',
        'member_id',
        'billing_customer_id',
        'base_amount',
        'base_amount_cents',
        'discount_amount',
        'discount_amount_cents',
        'discount_reason',
        'final_amount',
        'final_amount_cents',
        'status',
        'delivery_method',
        'validated_by',
        'validated_at',
        'invoice_id',
    ];

    protected $hidden = ['base_amount_cents', 'discount_amount_cents', 'final_amount_cents'];

    protected $appends = ['base_amount', 'discount_amount', 'final_amount'];

    protected function casts(): array
    {
        return [
            'status' => CampaignMemberStatus::class,
            'delivery_method' => InvoiceDeliveryMethod::class,
            'base_amount_cents' => 'integer',
            'discount_amount_cents' => 'integer',
            'final_amount_cents' => 'integer',
            'validated_at' => 'datetime',
        ];
    }

    /** @return Attribute<string|null, array<string, int|null>> */
    protected function baseAmount(): Attribute
    {
        return $this->moneyAttribute('base_amount');
    }

    /** @return Attribute<string|null, array<string, int|null>> */
    protected function discountAmount(): Attribute
    {
        return $this->moneyAttribute('discount_amount');
    }

    /** @return Attribute<string|null, array<string, int|null>> */
    protected function finalAmount(): Attribute
    {
        return $this->moneyAttribute('final_amount');
    }

    /**
     * @return BelongsTo<MembershipFeeCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MembershipFeeCampaign::class, 'membership_fee_campaign_id');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<BillingCustomer, $this>
     */
    public function billingCustomer(): BelongsTo
    {
        return $this->belongsTo(BillingCustomer::class);
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
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
