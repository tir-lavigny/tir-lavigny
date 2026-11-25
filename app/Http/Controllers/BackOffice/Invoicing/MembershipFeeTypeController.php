<?php

namespace App\Http\Controllers\BackOffice\Invoicing;

use App\Domains\Invoicing\Models\MembershipFeeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\Invoicing\StoreMembershipFeeTypeRequest;
use App\Http\Requests\BackOffice\Invoicing\UpdateMembershipFeeTypeRequest;
use App\MemberStatus;
use App\Support\Money;
use App\Traits\SearchesAccentInsensitively;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MembershipFeeTypeController extends Controller
{
    use SearchesAccentInsensitively;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', MembershipFeeType::class);

        $search = $request->input('search');
        $memberStatus = $request->input('member_status');
        $active = $request->input('active');

        $query = MembershipFeeType::query();

        if (in_array($memberStatus, MemberStatus::values(), true)) {
            $query->where('member_status', $memberStatus);
        }

        if ($active === 'active') {
            $query->where('is_active', true);
        } elseif ($active === 'inactive') {
            $query->where('is_active', false);
        }

        $this->whereAccentInsensitiveLike($query, ['name'], $search);

        return Inertia::render('backOffice/Invoicing/MembershipFeeTypes/Index', [
            'feeTypes' => $query->orderBy('member_status')->orderBy('name')->paginate(20)->withQueryString(),
            'filters' => [
                'search' => $search,
                'member_status' => $memberStatus,
                'active' => $active,
            ],
            'memberStatuses' => $this->memberStatusOptions(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', MembershipFeeType::class);

        return Inertia::render('backOffice/Invoicing/MembershipFeeTypes/Create', [
            'memberStatuses' => $this->memberStatusOptions(),
        ]);
    }

    public function store(StoreMembershipFeeTypeRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        MembershipFeeType::create([
            'name' => (string) $validated['name'],
            'member_status' => (string) $validated['member_status'],
            'description' => $validated['description'] ?? null,
            'amount_cents' => Money::toCents((string) $validated['amount']) ?? 0,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return to_route('bo.invoicing.membership-fee-types.index')
            ->with('success', 'Type de cotisation créé.');
    }

    public function edit(MembershipFeeType $membershipFeeType): Response
    {
        Gate::authorize('update', $membershipFeeType);

        return Inertia::render('backOffice/Invoicing/MembershipFeeTypes/Edit', [
            'feeType' => $membershipFeeType,
            'memberStatuses' => $this->memberStatusOptions(),
        ]);
    }

    public function update(UpdateMembershipFeeTypeRequest $request, MembershipFeeType $membershipFeeType): RedirectResponse
    {
        $validated = $request->validated();

        $membershipFeeType->update([
            'name' => (string) $validated['name'],
            'member_status' => (string) $validated['member_status'],
            'description' => $validated['description'] ?? null,
            'amount_cents' => Money::toCents((string) $validated['amount']) ?? 0,
            'is_active' => (bool) ($validated['is_active'] ?? $membershipFeeType->is_active),
        ]);

        return to_route('bo.invoicing.membership-fee-types.index')
            ->with('success', 'Type de cotisation mis à jour.');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function memberStatusOptions(): array
    {
        return array_map(
            fn (MemberStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            MemberStatus::cases(),
        );
    }
}
