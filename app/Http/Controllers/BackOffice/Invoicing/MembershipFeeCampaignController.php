<?php

namespace App\Http\Controllers\BackOffice\Invoicing;

use App\Domains\Invoicing\Actions\MembershipFees\CreateFeeCampaign;
use App\Domains\Invoicing\Actions\MembershipFees\ExcludeCampaignMember;
use App\Domains\Invoicing\Actions\MembershipFees\GenerateFeeInvoices;
use App\Domains\Invoicing\Actions\MembershipFees\IncludeCampaignMember;
use App\Domains\Invoicing\Actions\MembershipFees\IssueCampaignInvoices;
use App\Domains\Invoicing\Actions\MembershipFees\PreviewFeeCampaign;
use App\Domains\Invoicing\Actions\MembershipFees\UpdateCampaignMemberDelivery;
use App\Domains\Invoicing\Actions\MembershipFees\UpdateCampaignMemberDiscount;
use App\Domains\Invoicing\Actions\MembershipFees\UpdateFeeCampaign;
use App\Domains\Invoicing\Actions\MembershipFees\ValidateCampaign;
use App\Domains\Invoicing\Actions\MembershipFees\ValidateCampaignMember;
use App\Domains\Invoicing\Enums\CampaignMemberStatus;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\MembershipFeeCampaignStatus;
use App\Domains\Invoicing\Exceptions\InvoicingSettingsMissing;
use App\Domains\Invoicing\Exceptions\MembershipFeeException;
use App\Domains\Invoicing\Models\MembershipFeeCampaign;
use App\Domains\Invoicing\Models\MembershipFeeCampaignMember;
use App\Domains\Invoicing\Services\InvoicingSettingsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\Invoicing\StoreMembershipFeeCampaignRequest;
use App\Http\Requests\BackOffice\Invoicing\UpdateCampaignMemberDeliveryRequest;
use App\Http\Requests\BackOffice\Invoicing\UpdateCampaignMemberDiscountRequest;
use App\Http\Requests\BackOffice\Invoicing\UpdateMembershipFeeCampaignRequest;
use App\Support\Money;
use App\Traits\SearchesAccentInsensitively;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MembershipFeeCampaignController extends Controller
{
    use SearchesAccentInsensitively;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', MembershipFeeCampaign::class);

        $search = $request->input('search');
        $status = $request->input('status');
        $year = $request->integer('year') ?: null;

        $query = MembershipFeeCampaign::query()
            ->with('creator')
            ->withCount('members');

        if (in_array($status, array_column(MembershipFeeCampaignStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        if ($year !== null) {
            $query->where('year', $year);
        }

        $this->whereAccentInsensitiveLike($query, ['name'], $search);

        return Inertia::render('backOffice/Invoicing/MembershipFeeCampaigns/Index', [
            'campaigns' => $query->orderByDesc('year')->paginate(20)->withQueryString(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'year' => $year,
            ],
            'statuses' => $this->campaignStatusOptions(),
            'years' => $this->yearOptions(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', MembershipFeeCampaign::class);

        return Inertia::render('backOffice/Invoicing/MembershipFeeCampaigns/Create');
    }

    public function store(StoreMembershipFeeCampaignRequest $request, CreateFeeCampaign $create): RedirectResponse
    {
        $campaign = $create->execute($request->validated(), $request->user());

        return redirect()
            ->route('bo.invoicing.membership-fee-campaigns.show', $campaign)
            ->with('success', 'Campagne créée. Générez l’aperçu pour calculer les cotisations.');
    }

    public function show(
        MembershipFeeCampaign $membershipFeeCampaign,
        Request $request,
        InvoicingSettingsService $settings,
    ): Response {
        Gate::authorize('view', $membershipFeeCampaign);

        $search = $request->input('search');
        $memberStatus = $request->input('member_status');

        $members = $membershipFeeCampaign->members()
            ->with(['member', 'billingCustomer', 'feeType', 'invoice', 'validator'])
            ->when(in_array($memberStatus, array_column(CampaignMemberStatus::cases(), 'value'), true), fn (Builder $query) => $query->where('status', $memberStatus))
            ->when(filled($search), function (Builder $query) use ($search): void {
                $query->whereHas('member', function (Builder $query) use ($search): void {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('member_id')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('backOffice/Invoicing/MembershipFeeCampaigns/Show', [
            'campaign' => $membershipFeeCampaign->load('creator'),
            'members' => $members,
            'filters' => [
                'search' => $search,
                'member_status' => $memberStatus,
            ],
            'summary' => $this->summary($membershipFeeCampaign),
            'activityLogs' => $membershipFeeCampaign->activityLogs()
                ->with(['user', 'campaignMember.member'])
                ->limit(50)
                ->get(),
            'memberStatuses' => $this->memberStatusOptions(),
            'deliveryMethods' => $this->deliveryOptions(),
            'invoicingConfigured' => $settings->isConfigured(),
            'canIssueInvoices' => $request->user()?->can('issueInvoices', $membershipFeeCampaign) ?? false,
        ]);
    }

    public function edit(MembershipFeeCampaign $membershipFeeCampaign): Response|RedirectResponse
    {
        Gate::authorize('update', $membershipFeeCampaign);

        return Inertia::render('backOffice/Invoicing/MembershipFeeCampaigns/Edit', [
            'campaign' => $membershipFeeCampaign,
        ]);
    }

    public function update(
        UpdateMembershipFeeCampaignRequest $request,
        MembershipFeeCampaign $membershipFeeCampaign,
        UpdateFeeCampaign $update,
    ): RedirectResponse {
        try {
            $update->execute($membershipFeeCampaign, $request->validated(), $request->user());
        } catch (MembershipFeeException $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }

        return redirect()
            ->route('bo.invoicing.membership-fee-campaigns.show', $membershipFeeCampaign)
            ->with('success', 'Campagne mise à jour.');
    }

    public function preview(
        MembershipFeeCampaign $membershipFeeCampaign,
        PreviewFeeCampaign $preview,
    ): RedirectResponse {
        Gate::authorize('preview', $membershipFeeCampaign);

        try {
            $preview->execute($membershipFeeCampaign, request()->user());
        } catch (MembershipFeeException $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }

        return back()->with('success', 'Aperçu de la campagne généré.');
    }

    public function validateCampaign(
        MembershipFeeCampaign $membershipFeeCampaign,
        ValidateCampaign $validate,
    ): RedirectResponse {
        Gate::authorize('validate', $membershipFeeCampaign);

        try {
            $result = $validate->execute($membershipFeeCampaign, request()->user());
        } catch (MembershipFeeException $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }

        if ($result['errors'] !== []) {
            return back()
                ->withErrors(['action' => $result['errors'][0]])
                ->with('warning', "{$result['validated']} membre(s) validé(s). ".count($result['errors']).' membre(s) en erreur.');
        }

        return back()->with('success', 'Campagne validée.');
    }

    public function generateInvoices(
        MembershipFeeCampaign $membershipFeeCampaign,
        GenerateFeeInvoices $generate,
    ): RedirectResponse {
        Gate::authorize('generateInvoices', $membershipFeeCampaign);

        try {
            $result = $generate->execute($membershipFeeCampaign, request()->user());
        } catch (MembershipFeeException|InvoicingSettingsMissing $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }

        return back()->with('success', "{$result['generated']} facture(s) brouillon générée(s).");
    }

    public function issueInvoices(
        MembershipFeeCampaign $membershipFeeCampaign,
        IssueCampaignInvoices $issue,
    ): RedirectResponse {
        Gate::authorize('issueInvoices', $membershipFeeCampaign);

        try {
            $result = $issue->execute($membershipFeeCampaign, request()->user());
        } catch (MembershipFeeException $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }

        if ($result['issued'] === 0) {
            return back()->withErrors([
                'action' => $result['errors'][0] ?? 'Aucune facture n’a pu être émise.',
            ]);
        }

        if ($result['errors'] !== []) {
            return back()->with(
                'warning',
                "{$result['issued']} facture(s) émise(s). ".count($result['errors']).' échec(s) : '.$result['errors'][0],
            );
        }

        return back()->with('success', "{$result['issued']} facture(s) émise(s).");
    }

    public function updateDiscount(
        UpdateCampaignMemberDiscountRequest $request,
        MembershipFeeCampaign $membershipFeeCampaign,
        MembershipFeeCampaignMember $campaignMember,
        UpdateCampaignMemberDiscount $update,
    ): RedirectResponse {
        $this->assertCampaignMember($membershipFeeCampaign, $campaignMember);

        try {
            $update->execute(
                $campaignMember,
                Money::toCents((string) $request->validated('discount')) ?? 0,
                $request->validated('reason'),
                $request->user(),
            );
        } catch (MembershipFeeException $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }

        return back()->with('success', 'Remise mise à jour.');
    }

    public function updateDelivery(
        UpdateCampaignMemberDeliveryRequest $request,
        MembershipFeeCampaign $membershipFeeCampaign,
        MembershipFeeCampaignMember $campaignMember,
        UpdateCampaignMemberDelivery $update,
    ): RedirectResponse {
        $this->assertCampaignMember($membershipFeeCampaign, $campaignMember);

        try {
            $update->execute(
                $campaignMember,
                InvoiceDeliveryMethod::from((string) $request->validated('delivery_method')),
                $request->user(),
            );
        } catch (MembershipFeeException $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }

        return back()->with('success', 'Mode d’envoi mis à jour.');
    }

    public function validateMember(
        MembershipFeeCampaign $membershipFeeCampaign,
        MembershipFeeCampaignMember $campaignMember,
        ValidateCampaignMember $validate,
    ): RedirectResponse {
        Gate::authorize('manageMembers', $membershipFeeCampaign);
        $this->assertCampaignMember($membershipFeeCampaign, $campaignMember);

        try {
            $validate->execute($campaignMember, request()->user());
        } catch (MembershipFeeException $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }

        return back()->with('success', 'Membre validé.');
    }

    public function excludeMember(
        MembershipFeeCampaign $membershipFeeCampaign,
        MembershipFeeCampaignMember $campaignMember,
        ExcludeCampaignMember $exclude,
    ): RedirectResponse {
        Gate::authorize('manageMembers', $membershipFeeCampaign);
        $this->assertCampaignMember($membershipFeeCampaign, $campaignMember);

        try {
            $exclude->execute($campaignMember, request()->user());
        } catch (MembershipFeeException $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }

        return back()->with('success', 'Membre exclu de la campagne.');
    }

    public function includeMember(
        MembershipFeeCampaign $membershipFeeCampaign,
        MembershipFeeCampaignMember $campaignMember,
        IncludeCampaignMember $include,
    ): RedirectResponse {
        Gate::authorize('manageMembers', $membershipFeeCampaign);
        $this->assertCampaignMember($membershipFeeCampaign, $campaignMember);

        try {
            $include->execute($campaignMember, request()->user());
        } catch (MembershipFeeException $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }

        return back()->with('success', 'Membre réintégré dans la campagne.');
    }

    private function assertCampaignMember(
        MembershipFeeCampaign $campaign,
        MembershipFeeCampaignMember $campaignMember,
    ): void {
        abort_unless($campaignMember->membership_fee_campaign_id === $campaign->id, 404);
    }

    /**
     * @return array<string, int>
     */
    private function summary(MembershipFeeCampaign $campaign): array
    {
        $included = $campaign->includedMembers();

        return [
            'total' => $campaign->members()->count(),
            'included' => (clone $included)->count(),
            'pending' => (clone $included)->where('status', CampaignMemberStatus::Pending->value)->count(),
            'validated' => (clone $included)->where('status', CampaignMemberStatus::Validated->value)->count(),
            'excluded' => $campaign->members()->where('status', CampaignMemberStatus::Excluded->value)->count(),
            'invoiced' => (clone $included)->whereNotNull('invoice_id')->count(),
            'draft_invoices' => $campaign->invoices()->where('status', InvoiceStatus::Draft)->count(),
            'issued_invoices' => $campaign->invoices()->whereIn('status', [
                InvoiceStatus::Issued,
                InvoiceStatus::PartiallyPaid,
                InvoiceStatus::Paid,
            ])->count(),
            'base_cents' => (int) (clone $included)->sum('base_amount_cents'),
            'discount_cents' => (int) (clone $included)->sum('discount_amount_cents'),
            'final_cents' => (int) (clone $included)->sum('final_amount_cents'),
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function campaignStatusOptions(): array
    {
        return array_map(
            fn (MembershipFeeCampaignStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            MembershipFeeCampaignStatus::cases(),
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function memberStatusOptions(): array
    {
        return array_map(
            fn (CampaignMemberStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            CampaignMemberStatus::cases(),
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function deliveryOptions(): array
    {
        return array_map(
            fn (InvoiceDeliveryMethod $method): array => ['value' => $method->value, 'label' => $method->label()],
            InvoiceDeliveryMethod::cases(),
        );
    }

    /**
     * @return array<int, int>
     */
    private function yearOptions(): array
    {
        $currentYear = (int) now()->format('Y');

        return range($currentYear + 1, $currentYear - 5);
    }
}
