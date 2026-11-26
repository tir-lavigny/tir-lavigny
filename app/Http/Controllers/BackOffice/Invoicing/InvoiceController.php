<?php

namespace App\Http\Controllers\BackOffice\Invoicing;

use App\Domains\Invoicing\Actions\Invoices\CancelInvoice;
use App\Domains\Invoicing\Actions\Invoices\CreateInvoice;
use App\Domains\Invoicing\Actions\Invoices\DeleteDraftInvoice;
use App\Domains\Invoicing\Actions\Invoices\IssueInvoice;
use App\Domains\Invoicing\Actions\Invoices\UpdateDraftInvoice;
use App\Domains\Invoicing\Enums\FinancialDocumentStatus;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Enums\InvoiceItemType;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\InvoiceType;
use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Domains\Invoicing\Exceptions\InvoiceNotCancellable;
use App\Domains\Invoicing\Exceptions\InvoiceNotIssuable;
use App\Domains\Invoicing\Exceptions\InvoicingSettingsMissing;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoiceBalanceCalculator;
use App\Domains\Invoicing\Services\InvoiceDraftService;
use App\Domains\Invoicing\Services\InvoiceListQuery;
use App\Domains\Invoicing\Services\InvoicingSettingsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\Invoicing\CancelInvoiceRequest;
use App\Http\Requests\BackOffice\Invoicing\PreviewInvoiceRequest;
use App\Http\Requests\BackOffice\Invoicing\StoreInvoiceRequest;
use App\Http\Requests\BackOffice\Invoicing\UpdateInvoiceRequest;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function index(
        Request $request,
        InvoicingSettingsService $settings,
        InvoiceListQuery $invoices,
    ): Response {
        Gate::authorize('viewAny', Invoice::class);

        $search = $request->input('search');
        $status = $request->input('status');
        $type = $request->input('type');
        $year = $request->integer('year') ?: null;
        $overdue = $request->boolean('overdue');

        $paginated = $invoices
            ->filtered([
                'search' => is_string($search) ? $search : null,
                'status' => is_string($status) ? $status : null,
                'type' => is_string($type) ? $type : null,
                'year' => $year,
                'overdue' => $overdue,
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $paginated->through(fn (Invoice $invoice): Invoice => $invoices->applyBalanceAttributes($invoice));

        return Inertia::render('backOffice/Invoicing/Invoices/Index', [
            'invoices' => $paginated,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'type' => $type,
                'year' => $year,
                'overdue' => $overdue,
            ],
            'statuses' => $this->statusOptions(),
            'types' => $this->typeOptions(),
            'years' => $this->yearOptions(),
            'invoicingConfigured' => $settings->isConfigured(),
        ]);
    }

    public function create(InvoicingSettingsService $settings): Response|RedirectResponse
    {
        Gate::authorize('create', Invoice::class);

        if (! $settings->isConfigured()) {
            return to_route('bo.invoicing.settings.edit')
                ->withErrors(['settings' => 'Configurez les paramètres de facturation avant de créer une facture.']);
        }

        return Inertia::render('backOffice/Invoicing/Invoices/Create', [
            'billingCustomers' => $this->billingCustomerOptions(),
            'deliveryMethods' => $this->deliveryOptions(),
            'itemTypes' => $this->itemTypeOptions(),
        ]);
    }

    public function store(StoreInvoiceRequest $request, CreateInvoice $create): RedirectResponse
    {
        try {
            $invoice = $create->execute($request->validated(), $request->user());
        } catch (InvoicingSettingsMissing $exception) {
            return to_route('bo.invoicing.settings.edit')
                ->withErrors(['settings' => $exception->getMessage()]);
        }

        return redirect()
            ->route('bo.invoicing.invoices.show', $invoice)
            ->with('success', 'Facture brouillon créée.');
    }

    public function show(Invoice $invoice, InvoiceBalanceCalculator $balances): Response
    {
        Gate::authorize('view', $invoice);

        $invoice->load([
            'items.product',
            'billingCustomer',
            'activityLogs.user',
            'creditNoteApplications.creditNote',
            'creditNoteApplications.reversals',
            'paymentAllocations.payment.creator',
            'paymentAllocations.reverser',
            'document',
            'deliveries.processor',
            'reminders.processor',
        ]);

        $outstandingCents = $balances->outstandingCents($invoice);

        return Inertia::render('backOffice/Invoicing/Invoices/Show', [
            'invoice' => $invoice,
            'balance' => [
                'paid_cents' => $balances->paidCents($invoice),
                'credited_cents' => $balances->creditedCents($invoice),
                'outstanding_cents' => $outstandingCents,
                'days_overdue' => $balances->daysOverdue($invoice, outstandingCents: $outstandingCents),
            ],
            'paymentMethods' => $this->paymentMethodOptions(),
            'paymentIdempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function downloadPdf(Invoice $invoice): StreamedResponse
    {
        Gate::authorize('view', $invoice);

        $document = $invoice->document()->first();

        abort_unless(
            $document !== null
                && $document->isStored()
                && Storage::disk($document->disk)->exists($document->path),
            404,
        );

        return Storage::disk($document->disk)->download(
            $document->path,
            "facture-{$invoice->invoice_number}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function edit(Invoice $invoice): Response
    {
        Gate::authorize('update', $invoice);

        $invoice->load('items');

        return Inertia::render('backOffice/Invoicing/Invoices/Edit', [
            'invoice' => $invoice,
            'billingCustomers' => $this->billingCustomerOptions(),
            'deliveryMethods' => $this->deliveryOptions(),
            'itemTypes' => $this->itemTypeOptions(),
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
        } catch (InvoiceNotIssuable|InvoicingSettingsMissing $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }

        if ($issued->document?->status === FinancialDocumentStatus::Failed) {
            return redirect()
                ->route('bo.invoicing.invoices.show', $issued)
                ->with('warning', 'Facture émise, mais le PDF n’a pas pu être archivé. Réessayez l’archivage depuis la fiche.');
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
            return back()->withErrors(['action' => $exception->getMessage()]);
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
     * @return array<int, array{id: int, name: string, email: string|null, preferred_invoice_delivery: string}>
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
                'preferred_invoice_delivery' => $customer->preferred_invoice_delivery->value,
            ])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    private function itemTypeOptions(): array
    {
        return array_map(
            fn (InvoiceItemType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
            ],
            InvoiceItemType::cases(),
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
    private function paymentMethodOptions(): array
    {
        return array_map(
            fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()],
            PaymentMethod::cases(),
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
