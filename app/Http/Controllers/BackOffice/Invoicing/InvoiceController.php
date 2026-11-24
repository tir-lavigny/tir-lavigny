<?php

namespace App\Http\Controllers\BackOffice\Invoicing;

use App\Domains\Invoicing\Actions\Invoices\CancelInvoice;
use App\Domains\Invoicing\Actions\Invoices\CreateInvoice;
use App\Domains\Invoicing\Actions\Invoices\DeleteDraftInvoice;
use App\Domains\Invoicing\Actions\Invoices\IssueInvoice;
use App\Domains\Invoicing\Actions\Invoices\UpdateDraftInvoice;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\InvoiceType;
use App\Domains\Invoicing\Exceptions\InvoiceNotCancellable;
use App\Domains\Invoicing\Exceptions\InvoiceNotIssuable;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoiceDraftService;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\Invoicing\CancelInvoiceRequest;
use App\Http\Requests\BackOffice\Invoicing\PreviewInvoiceRequest;
use App\Http\Requests\BackOffice\Invoicing\StoreInvoiceRequest;
use App\Http\Requests\BackOffice\Invoicing\UpdateInvoiceRequest;
use App\Support\Money;
use App\Traits\SearchesAccentInsensitively;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    use SearchesAccentInsensitively;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Invoice::class);

        $search = $request->input('search');
        $status = $request->input('status');
        $type = $request->input('type');
        $year = $request->integer('year') ?: null;

        $query = Invoice::query()->with('billingCustomer');

        if (in_array($status, array_column(InvoiceStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        if (in_array($type, array_column(InvoiceType::cases(), 'value'), true)) {
            $query->where('type', $type);
        }

        if ($year !== null) {
            $query->where(function (Builder $query) use ($year): void {
                $query->whereYear('issue_date', $year)
                    ->orWhere(function (Builder $query) use ($year): void {
                        $query->whereNull('issue_date')->whereYear('created_at', $year);
                    });
            });
        }

        if (filled($search)) {
            $query->where(function (Builder $query) use ($search): void {
                $this->whereAccentInsensitiveLike($query, ['recipient_name'], $search);
                $query->orWhere('invoice_number', 'like', "%{$search}%");
            });
        }

        return Inertia::render('backOffice/Invoicing/Invoices/Index', [
            'invoices' => $query->orderByDesc('created_at')->orderByDesc('id')->paginate(20)->withQueryString(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'type' => $type,
                'year' => $year,
            ],
            'statuses' => $this->statusOptions(),
            'types' => $this->typeOptions(),
            'years' => $this->yearOptions(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Invoice::class);

        return Inertia::render('backOffice/Invoicing/Invoices/Create', [
            'billingCustomers' => $this->billingCustomerOptions(),
            'deliveryMethods' => $this->deliveryOptions(),
        ]);
    }

    public function store(StoreInvoiceRequest $request, CreateInvoice $create): RedirectResponse
    {
        $invoice = $create->execute($request->validated(), $request->user());

        return redirect()
            ->route('bo.invoicing.invoices.show', $invoice)
            ->with('success', 'Facture brouillon créée.');
    }

    public function show(Invoice $invoice): Response
    {
        Gate::authorize('view', $invoice);

        $invoice->load(['items.product', 'billingCustomer', 'activityLogs.user']);

        return Inertia::render('backOffice/Invoicing/Invoices/Show', [
            'invoice' => $invoice,
        ]);
    }

    public function edit(Invoice $invoice): Response
    {
        Gate::authorize('update', $invoice);

        $invoice->load('items');

        return Inertia::render('backOffice/Invoicing/Invoices/Edit', [
            'invoice' => $invoice,
            'billingCustomers' => $this->billingCustomerOptions(),
            'deliveryMethods' => $this->deliveryOptions(),
        ]);
    }

    public function update(
        UpdateInvoiceRequest $request,
        Invoice $invoice,
        UpdateDraftInvoice $update,
    ): RedirectResponse {
        $invoice = $update->execute($invoice, $request->validated(), $request->user());

        return redirect()
            ->route('bo.invoicing.invoices.show', $invoice)
            ->with('success', 'Facture brouillon mise à jour.');
    }

    public function destroy(Invoice $invoice, DeleteDraftInvoice $delete): RedirectResponse
    {
        Gate::authorize('delete', $invoice);

        $delete->execute($invoice, request()->user());

        return redirect()
            ->route('bo.invoicing.invoices.index')
            ->with('success', 'Facture brouillon supprimée.');
    }

    public function issue(Invoice $invoice, IssueInvoice $issue): RedirectResponse
    {
        Gate::authorize('issue', $invoice);

        try {
            $issued = $issue->execute($invoice, request()->user());
        } catch (InvoiceNotIssuable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('bo.invoicing.invoices.show', $issued)
            ->with('success', 'Facture émise.');
    }

    public function cancel(
        CancelInvoiceRequest $request,
        Invoice $invoice,
        CancelInvoice $cancel,
    ): RedirectResponse {
        try {
            $cancelled = $cancel->execute($invoice, $request->validated('reason'), $request->user());
        } catch (InvoiceNotCancellable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('bo.invoicing.invoices.show', $cancelled)
            ->with('success', 'Facture annulée.');
    }

    public function preview(PreviewInvoiceRequest $request, InvoiceDraftService $draftService): JsonResponse
    {
        $prepared = $draftService->prepareItems($request->validated('items'));

        return response()->json([
            'lines' => array_map(fn (array $line): array => [
                'total_cents' => $line['total_cents'],
                'total' => Money::fromCents($line['total_cents']),
            ], $prepared['lines']),
            'subtotal_cents' => $prepared['subtotal_cents'],
            'subtotal' => Money::fromCents($prepared['subtotal_cents']),
            'discounts_cents' => $prepared['discounts_cents'],
            'discounts' => Money::fromCents($prepared['discounts_cents']),
            'total_cents' => $prepared['total_cents'],
            'total' => Money::fromCents($prepared['total_cents']),
        ]);
    }

    /**
     * @return array<int, array{id: int, name: string, email: string|null}>
     */
    private function billingCustomerOptions(): array
    {
        return BillingCustomer::query()
            ->orderByRaw('COALESCE(last_name, organization_name) asc')
            ->orderBy('first_name')
            ->get()
            ->map(fn (BillingCustomer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->displayName(),
                'email' => $customer->email,
            ])
            ->all();
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
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (InvoiceStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            InvoiceStatus::cases(),
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function typeOptions(): array
    {
        return array_map(
            fn (InvoiceType $type): array => ['value' => $type->value, 'label' => $type->label()],
            InvoiceType::cases(),
        );
    }

    /**
     * @return array<int, int>
     */
    private function yearOptions(): array
    {
        $currentYear = (int) now()->format('Y');

        return range($currentYear, $currentYear - 5);
    }
}
