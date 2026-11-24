<?php

namespace App\Domains\Invoicing\Models;

use App\Domains\Invoicing\Enums\MembershipFeeCampaignStatus;
use App\Models\User;
use Database\Factories\Domains\Invoicing\Models\MembershipFeeCampaignFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
