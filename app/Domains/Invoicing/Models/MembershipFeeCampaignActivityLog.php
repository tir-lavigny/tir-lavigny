<?php

namespace App\Domains\Invoicing\Models;

use App\Models\User;
use Database\Factories\Domains\Invoicing\Models\MembershipFeeCampaignActivityLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class MembershipFeeCampaignActivityLog extends Model
{
    /** @use HasFactory<MembershipFeeCampaignActivityLogFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $appends = ['action_label'];

    protected $fillable = [
        'membership_fee_campaign_id',
        'membership_fee_campaign_member_id',
        'user_id',
        'action',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MembershipFeeCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MembershipFeeCampaign::class, 'membership_fee_campaign_id');
    }

    /**
     * @return BelongsTo<MembershipFeeCampaignMember, $this>
     */
    public function campaignMember(): BelongsTo
    {
        return $this->belongsTo(MembershipFeeCampaignMember::class, 'membership_fee_campaign_member_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'campaign.created' => 'Campagne créée',
            'campaign.updated' => 'Campagne modifiée',
            'campaign.preview_generated' => 'Aperçu généré',
            'campaign.validated' => 'Campagne validée',
            'campaign.base_amounts_refreshed' => 'Montants de base recalculés',
            'campaign.invoices_generated' => 'Factures brouillon générées',
            'campaign.invoices_issued' => 'Factures émises',
            'campaign.invoice_draft_deleted' => 'Facture brouillon supprimée',
            'campaign_member.base_amount_updated' => 'Montant de base mis à jour',
            'campaign_member.discount_updated' => 'Remise modifiée',
            'campaign_member.exempted' => 'Exonération totale accordée',
            'campaign_member.delivery_changed' => 'Mode d’envoi modifié',
            'campaign_member.validated' => 'Membre validé',
            'campaign_member.excluded' => 'Membre exclu',
            'campaign_member.included' => 'Membre réintégré',
            default => $this->action,
        };
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Membership fee campaign activity logs cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Membership fee campaign activity logs cannot be deleted.');
        });
    }
}
