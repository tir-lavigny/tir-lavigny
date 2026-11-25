<?php

namespace App\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\CampaignMemberStatus;
use App\Domains\Invoicing\Enums\MembershipFeeCampaignStatus;
use App\Domains\Invoicing\Policies\MembershipFeeCampaignPolicy;
use App\Models\User;
use Database\Factories\Domains\Invoicing\Models\MembershipFeeCampaignFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property MembershipFeeCampaignStatus $status
 * @property Carbon $issue_date
 * @property Carbon $due_date
 * @property int $year
 * @property string $name
 */
#[UsePolicy(MembershipFeeCampaignPolicy::class)]
class MembershipFeeCampaign extends Model
{
    /** @use HasFactory<MembershipFeeCampaignFactory> */
    use HasFactory;

    protected $fillable = [
        'year',
        'name',
        'issue_date',
        'due_date',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'issue_date' => 'date',
            'due_date' => 'date',
            'status' => MembershipFeeCampaignStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<MembershipFeeCampaignMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(MembershipFeeCampaignMember::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<MembershipFeeCampaignActivityLog, $this>
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(MembershipFeeCampaignActivityLog::class)->latest('created_at')->latest('id');
    }

    /**
     * @return HasMany<MembershipFeeCampaignMember, $this>
     */
    public function includedMembers(): HasMany
    {
        return $this->members()->where('status', '!=', CampaignMemberStatus::Excluded->value);
    }

    public function hasPendingMembers(): bool
    {
        return $this->includedMembers()->where('status', CampaignMemberStatus::Pending->value)->exists();
    }

    public function hasUninvoicedMembers(): bool
    {
        return $this->includedMembers()->whereNull('invoice_id')->exists();
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }
}
