<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Enums\CampaignMemberStatus;
use App\Domains\Invoicing\Models\MembershipFeeCampaign;
use App\Domains\Invoicing\Models\MembershipFeeCampaignMember;
use App\Models\Member;
use App\Support\Money;
use App\Traits\SearchesAccentInsensitively;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipFeeCampaignExportService
{
    use SearchesAccentInsensitively;

    /**
     * @param  array{search?: string|null, member_status?: string|null}  $filters
     * @return HasMany<MembershipFeeCampaignMember, MembershipFeeCampaign>
     */
    public function query(MembershipFeeCampaign $campaign, array $filters = []): HasMany
    {
        $search = $filters['search'] ?? null;
        $memberStatus = $filters['member_status'] ?? null;

        return $campaign->members()
            ->with(['member', 'feeType', 'invoice'])
            ->when(
                is_string($memberStatus) && in_array($memberStatus, array_column(CampaignMemberStatus::cases(), 'value'), true),
                fn (Builder $query) => $query->where('status', $memberStatus),
            )
            ->when(is_string($search) && filled($search), function (Builder $query) use ($search): void {
                $memberQuery = Member::query();
                $this->whereAccentInsensitiveLike($memberQuery, ['first_name', 'last_name', 'email'], $search);
                $query->whereIn('member_id', $memberQuery->select('id'));
            })
            ->orderBy('member_id');
    }

    /**
     * @return array<int, string>
     */
    public function headers(): array
    {
        return [
            'Membre',
            'Email',
            'Statut',
            'Type de cotisation',
            'Montant de base CHF',
            'Remise CHF',
            'Motif de remise',
            'Montant final CHF',
            'Exonération totale',
            'Mode d’envoi',
            'Validation',
            'Numéro de facture',
        ];
    }

    /**
     * @param  HasMany<MembershipFeeCampaignMember, MembershipFeeCampaign>  $query
     * @return iterable<int, array<int, string>>
     */
    public function rows(HasMany $query): iterable
    {
        foreach ($query->cursor() as $campaignMember) {
            $member = $campaignMember->member;
            $name = $member === null
                ? ''
                : trim($member->first_name.' '.$member->last_name);
            $email = $member === null ? '' : $member->email;

            yield [
                $name,
                $email ?? '',
                $campaignMember->status->label(),
                $campaignMember->fee_type_name ?? '',
                Money::fromCents($campaignMember->base_amount_cents) ?? '0.00',
                Money::fromCents($campaignMember->discount_amount_cents) ?? '0.00',
                $campaignMember->discount_reason ?? '',
                Money::fromCents($campaignMember->final_amount_cents) ?? '0.00',
                $campaignMember->is_full_exemption ? 'Oui' : 'Non',
                $campaignMember->delivery_method->label(),
                $campaignMember->validated_at?->format('d.m.Y H:i') ?? '',
                optional($campaignMember->invoice)->invoice_number ?? '',
            ];
        }
    }

    /**
     * @return array<int, float>
     */
    public function widths(): array
    {
        return [
            1 => 28,
            2 => 30,
            3 => 14,
            4 => 28,
            5 => 20,
            6 => 14,
            7 => 32,
            8 => 20,
            9 => 18,
            10 => 14,
            11 => 18,
            12 => 20,
        ];
    }
}
