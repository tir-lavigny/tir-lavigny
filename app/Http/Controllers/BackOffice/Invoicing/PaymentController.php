<?php

namespace App\Http\Controllers\BackOffice\Invoicing;

use App\Domains\Invoicing\Actions\Payments\AllocatePayment;
use App\Domains\Invoicing\Actions\Payments\RegisterPayment;
use App\Domains\Invoicing\Actions\Payments\ReversePaymentAllocation;
use App\Domains\Invoicing\Actions\Payments\UpdatePayment;
use App\Domains\Invoicing\Actions\Payments\VoidPayment;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Domains\Invoicing\Exceptions\PaymentException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoicePayment;
use App\Domains\Invoicing\Models\Payment;
use App\Domains\Invoicing\Services\PaymentBalanceCalculator;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\Invoicing\AllocatePaymentRequest;
use App\Http\Requests\BackOffice\Invoicing\ReversePaymentAllocationRequest;
use App\Http\Requests\BackOffice\Invoicing\StorePaymentRequest;
use App\Http\Requests\BackOffice\Invoicing\UpdatePaymentRequest;
use App\Http\Requests\BackOffice\Invoicing\VoidPaymentRequest;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->canAny(['view payments', 'manage invoicing']), 403);

        $search = $request->input('search');
        $method = $request->input('method');
        $status = $request->input('status');
        $allocationStatus = $request->input('allocation_status');
        $year = $request->integer('year') ?: null;

        $query = Payment::query()
            ->with('creator')
            ->withSum(['allocations as allocated_cents' => fn (Builder $query) => $query->whereNull('reversed_at')], 'amount_cents');

        if (in_array($method, array_column(PaymentMethod::cases(), 'value'), true)) {
            $query->where('method', $method);
        }

        if ($status === 'voided') {
            $query->whereNotNull('voided_at');
        } elseif ($status === 'active') {
            $query->whereNull('voided_at');
        }

        if (in_array($allocationStatus, ['unallocated', 'partially_allocated', 'fully_allocated'], true)) {
            $query->whereNull('voided_at')->allocationStatus($allocationStatus);
        }

        if ($year !== null) {
            $query->whereYear('payment_date', $year);
        }

        if (filled($search)) {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('reference', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $payments = $query->orderByDesc('payment_date')->orderByDesc('id')->paginate(20)->withQueryString();
        $payments->through(function (Payment $payment): Payment {
            $allocated = (int) ($payment->getAttribute('allocated_cents') ?? 0);

            $payment->setAttribute('allocated_cents', $allocated);
            $payment->setAttribute(
                'unallocated_cents',
                $payment->isVoided() ? 0 : max(0, $payment->amount_cents - $allocated),
            );
            $payment->setAttribute('is_voided', $payment->isVoided());

            return $payment;
        });

        return Inertia::render('backOffice/Invoicing/Payments/Index', [
            'payments' => $payments,
            'filters' => [
                'search' => $search,
                'method' => $method,
                'status' => $status,
                'allocation_status' => $allocationStatus,
                'year' => $year,
            ],
            'methods' => $this->methodOptions(),
            'statuses' => $this->statusOptions(),
            'allocationStatuses' => $this->allocationStatusOptions(),
            'years' => $this->yearOptions(),
        ]);
    }

    public function create(): Response
    {
        abort_unless(request()->user()?->canAny(['create payments', 'manage invoicing']), 403);

        return Inertia::render('backOffice/Invoicing/Payments/Create', [
            'methods' => $this->methodOptions(),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(StorePaymentRequest $request, RegisterPayment $register): RedirectResponse
    {
        $validated = $request->validated();
        $data = [
            'amount_cents' => Money::toCents($validated['amount']) ?? 0,
            'method' => (string) $validated['method'],
            'payment_date' => (string) $validated['payment_date'],
            'reference' => $validated['reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'idempotency_key' => (string) $validated['idempotency_key'],
        ];

        $payment = $register->execute($data, $request->user());

        return redirect()
            ->route('bo.invoicing.payments.show', $payment)
            ->with('success', 'Paiement enregistré.');
    }

    public function storeForInvoice(
        StorePaymentRequest $request,
        Invoice $invoice,
        RegisterPayment $register,
    ): RedirectResponse {
        $validated = $request->validated();
        $data = [
            'amount_cents' => Money::toCents($validated['amount']) ?? 0,
            'method' => (string) $validated['method'],
            'payment_date' => (string) $validated['payment_date'],
            'reference' => $validated['reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'idempotency_key' => (string) $validated['idempotency_key'],
        ];

        try {
            $register->execute($data, $request->user(), $invoice);
        } catch (PaymentException $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }

        return redirect()
            ->route('bo.invoicing.invoices.show', $invoice)
            ->with('success', 'Paiement enregistré et imputé à la facture.');
    }

    public function show(Payment $payment, PaymentBalanceCalculator $balances): Response
    {
        $payment->load([
            'creator',
            'voider',
            'allocations.invoice.billingCustomer',
            'allocations.reverser',
        ]);

        $allocated = $balances->allocatedCents($payment);
        $available = $balances->availableCents($payment);
        $canAllocate = request()->user()?->canAny(['create payments', 'manage invoicing']) ?? false;

        $allocatableInvoices = $canAllocate
            ? Invoice::query()
                ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
                ->withSum(['paymentAllocations as paid_cents' => fn (Builder $query) => $query->whereNull('reversed_at')], 'amount_cents')
                ->withSum('creditNoteApplications as credited_cents', 'amount_cents')
                ->withSum('creditNoteApplicationReversals as credited_reversed_cents', 'amount_cents')
                ->orderBy('due_date')
                ->orderBy('invoice_number')
                ->get()
                ->map(function (Invoice $invoice): array {
                    $credited = max(
                        0,
                        (int) ($invoice->getAttribute('credited_cents') ?? 0)
                            - (int) ($invoice->getAttribute('credited_reversed_cents') ?? 0),
                    );
                    $outstanding = max(
                        0,
                        $invoice->total_cents - (int) ($invoice->getAttribute('paid_cents') ?? 0) - $credited,
                    );

                    return [
                        'id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'recipient_name' => $invoice->recipient_name,
                        'due_date' => $invoice->due_date?->toDateString(),
                        'outstanding_cents' => $outstanding,
                    ];
                })
                ->filter(fn (array $invoice): bool => $invoice['outstanding_cents'] > 0)
                ->values()
                ->all()
            : [];

        return Inertia::render('backOffice/Invoicing/Payments/Show', [
            'payment' => $payment,
            'allocatedCents' => $allocated,
            'availableCents' => $available,
            'allocatableInvoices' => $allocatableInvoices,
            'allocationIdempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function edit(Payment $payment, PaymentBalanceCalculator $balances): Response|RedirectResponse
    {
        if ($payment->isVoided()) {
            return redirect()
                ->route('bo.invoicing.payments.show', $payment)
                ->withErrors(['action' => 'Un paiement annulé ne peut plus être modifié.']);
        }

        $payment->load('creator');

        return Inertia::render('backOffice/Invoicing/Payments/Edit', [
            'payment' => $payment,
            'methods' => $this->methodOptions(),
            'hasActiveAllocations' => $balances->allocatedCents($payment) > 0,
        ]);
    }

    public function update(
        UpdatePaymentRequest $request,
        Payment $payment,
        UpdatePayment $update,
    ): RedirectResponse {
        $validated = $request->validated();
        $data = [
            'amount_cents' => Money::toCents($validated['amount']) ?? 0,
            'method' => (string) $validated['method'],
            'payment_date' => (string) $validated['payment_date'],
            'reference' => $validated['reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];

        try {
            $update->execute($payment, $data);
        } catch (PaymentException $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }

        return redirect()
            ->route('bo.invoicing.payments.show', $payment)
            ->with('success', 'Paiement mis à jour.');
    }

    public function void(
        VoidPaymentRequest $request,
        Payment $payment,
        VoidPayment $void,
    ): RedirectResponse {
        try {
            $void->execute($payment, $request->validated('reason'), $request->user());
        } catch (PaymentException $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }

        return back()->with('success', 'Paiement annulé.');
    }

    public function allocate(
        AllocatePaymentRequest $request,
        Payment $payment,
        AllocatePayment $allocate,
    ): RedirectResponse {
        $invoice = Invoice::query()->findOrFail((int) $request->validated('invoice_id'));
        $amountCents = Money::toCents($request->validated('amount')) ?? 0;

        try {
            $allocate->execute(
                $payment,
                $invoice,
                $amountCents,
                $request->user(),
                $request->validated('idempotency_key'),
            );
        } catch (PaymentException $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }

        return back()->with('success', 'Paiement imputé.');
    }

    public function reverseAllocation(
        ReversePaymentAllocationRequest $request,
        InvoicePayment $invoicePayment,
        ReversePaymentAllocation $reverse,
    ): RedirectResponse {
        try {
            $reverse->execute($invoicePayment, $request->validated('reason'), $request->user());
        } catch (PaymentException $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }

        return back()->with('success', 'Imputation annulée.');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function methodOptions(): array
    {
        return array_map(
            fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()],
            PaymentMethod::cases(),
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['value' => 'active', 'label' => 'Actifs'],
            ['value' => 'voided', 'label' => 'Annulés'],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function allocationStatusOptions(): array
    {
        return [
            ['value' => 'unallocated', 'label' => 'Non alloués'],
            ['value' => 'partially_allocated', 'label' => 'Partiellement alloués'],
            ['value' => 'fully_allocated', 'label' => 'Entièrement alloués'],
        ];
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
