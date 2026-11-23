<?php

namespace App\Http\Controllers\BackOffice\Invoicing;

use App\Domains\Invoicing\Actions\Customers\CreateBillingCustomer;
use App\Domains\Invoicing\Actions\Customers\SyncMemberBillingCustomer;
use App\Domains\Invoicing\Actions\Customers\UpdateBillingCustomer;
use App\Domains\Invoicing\Enums\BillingCustomerType;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Exceptions\CannotInvoiceAnonymizedMember;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\Invoicing\StoreBillingCustomerRequest;
use App\Http\Requests\BackOffice\Invoicing\SyncMemberBillingCustomerRequest;
use App\Http\Requests\BackOffice\Invoicing\UpdateBillingCustomerRequest;
use App\Models\Member;
use App\Traits\SearchesAccentInsensitively;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BillingCustomerController extends Controller
{
    use SearchesAccentInsensitively;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', BillingCustomer::class);

        $search = $request->input('search');
        $type = $request->input('type');
        $delivery = $request->input('delivery');

        $query = BillingCustomer::query()->with('member');

        if (in_array($type, array_column(BillingCustomerType::cases(), 'value'), true)) {
            $query->where('type', $type);
        }

        if (in_array($delivery, array_column(InvoiceDeliveryMethod::cases(), 'value'), true)) {
            $query->where('preferred_invoice_delivery', $delivery);
        }

        $this->whereAccentInsensitiveLike($query, ['first_name', 'last_name', 'organization_name', 'email'], $search);

        return Inertia::render('backOffice/Invoicing/BillingCustomers/Index', [
            'billingCustomers' => $query
                ->orderByRaw('COALESCE(last_name, organization_name) asc')
                ->orderBy('first_name')
                ->paginate(20)
                ->withQueryString(),
            'filters' => [
                'search' => $search,
                'type' => $type,
                'delivery' => $delivery,
            ],
            'types' => $this->typeOptions(),
            'deliveryMethods' => $this->deliveryOptions(),
            'members' => $request->user()->can('create', BillingCustomer::class)
                ? Member::query()
                    ->active()
                    ->whereDoesntHave('billingCustomer')
                    ->orderByName()
                    ->get(['id', 'first_name', 'last_name', 'email'])
                : [],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('backOffice/Invoicing/BillingCustomers/Create', [
            'types' => $this->typeOptions(),
            'deliveryMethods' => $this->deliveryOptions(),
        ]);
    }

    public function store(StoreBillingCustomerRequest $request, CreateBillingCustomer $create): RedirectResponse
    {
        $billingCustomer = $create->execute($request->validated());

        return redirect()
            ->route('bo.invoicing.billing-customers.edit', $billingCustomer)
            ->with('success', 'Client de facturation créé.');
    }

    public function edit(BillingCustomer $billingCustomer): Response
    {
        $billingCustomer->load('member');

        return Inertia::render('backOffice/Invoicing/BillingCustomers/Edit', [
            'billingCustomer' => $billingCustomer,
            'types' => $this->typeOptions(),
            'deliveryMethods' => $this->deliveryOptions(),
        ]);
    }

    public function update(
        UpdateBillingCustomerRequest $request,
        BillingCustomer $billingCustomer,
        UpdateBillingCustomer $update,
    ): RedirectResponse {
        $update->execute($billingCustomer, $request->validated());

        return redirect()
            ->route('bo.invoicing.billing-customers.edit', $billingCustomer)
            ->with('success', 'Client de facturation mis à jour.');
    }

    public function destroy(BillingCustomer $billingCustomer): RedirectResponse
    {
        Gate::authorize('delete', $billingCustomer);

        if ($billingCustomer->invoices()->exists() || $billingCustomer->campaignMembers()->exists()) {
            return back()->with('error', 'Impossible de supprimer ce client car il est lié à des factures.');
        }

        $billingCustomer->delete();

        return redirect()
            ->route('bo.invoicing.billing-customers.index')
            ->with('success', 'Client de facturation supprimé.');
    }

    public function syncMember(
        SyncMemberBillingCustomerRequest $request,
        SyncMemberBillingCustomer $sync,
    ): RedirectResponse {
        $member = Member::findOrFail($request->integer('member_id'));

        try {
            $billingCustomer = $sync->execute($member);
        } catch (CannotInvoiceAnonymizedMember) {
            return back()->with('error', 'Ce membre est anonymisé et ne peut pas être facturé.');
        }

        return redirect()
            ->route('bo.invoicing.billing-customers.edit', $billingCustomer)
            ->with('success', 'Client de facturation créé depuis le membre.');
    }

    public function syncExisting(
        BillingCustomer $billingCustomer,
        SyncMemberBillingCustomer $sync,
    ): RedirectResponse {
        Gate::authorize('update', $billingCustomer);

        if ($billingCustomer->member === null) {
            return back()->with('error', 'Ce client de facturation n\'est pas lié à un membre.');
        }

        try {
            $sync->execute($billingCustomer->member);
        } catch (CannotInvoiceAnonymizedMember) {
            return back()->with('error', 'Ce membre est anonymisé et ne peut pas être facturé.');
        }

        return back()->with('success', 'Client de facturation synchronisé avec le membre.');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function typeOptions(): array
    {
        return array_map(
            fn (BillingCustomerType $type): array => ['value' => $type->value, 'label' => $type->label()],
            BillingCustomerType::cases(),
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
}
