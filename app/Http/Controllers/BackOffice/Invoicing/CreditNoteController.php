<?php

namespace App\Http\Controllers\BackOffice\Invoicing;

use App\Domains\Invoicing\Actions\CreditNotes\ApplyCreditNoteToInvoice;
use App\Domains\Invoicing\Actions\CreditNotes\CreateCreditNote;
use App\Domains\Invoicing\Actions\CreditNotes\CreateCreditNoteReversal;
use App\Domains\Invoicing\Actions\CreditNotes\DeleteDraftCreditNote;
use App\Domains\Invoicing\Actions\CreditNotes\IssueCreditNote;
use App\Domains\Invoicing\Actions\CreditNotes\IssueCreditNoteReversal;
use App\Domains\Invoicing\Actions\CreditNotes\RecordCreditNoteRefund;
use App\Domains\Invoicing\Actions\CreditNotes\ReverseCreditNoteApplication;
use App\Domains\Invoicing\Actions\CreditNotes\UpdateDraftCreditNote;
use App\Domains\Invoicing\Enums\CreditNoteStatus;
use App\Domains\Invoicing\Exceptions\CreditNoteNotApplicable;
use App\Domains\Invoicing\Exceptions\CreditNoteNotIssuable;
use App\Domains\Invoicing\Exceptions\CreditNoteReversalNotIssuable;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CreditNoteApplication;
use App\Domains\Invoicing\Models\CreditNoteReversal;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\CreditNoteBalanceCalculator;
use App\Domains\Invoicing\Services\CreditNoteCreditLimit;
use App\Domains\Invoicing\Services\CreditNoteDraftService;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\Invoicing\ApplyCreditNoteRequest;
use App\Http\Requests\BackOffice\Invoicing\IssueCreditNoteRequest;
use App\Http\Requests\BackOffice\Invoicing\IssueCreditNoteReversalRequest;
use App\Http\Requests\BackOffice\Invoicing\PreviewCreditNoteRequest;
use App\Http\Requests\BackOffice\Invoicing\RecordCreditNoteRefundRequest;
use App\Http\Requests\BackOffice\Invoicing\ReverseCreditNoteApplicationRequest;
use App\Http\Requests\BackOffice\Invoicing\StoreCreditNoteRequest;
use App\Http\Requests\BackOffice\Invoicing\StoreCreditNoteReversalRequest;
use App\Http\Requests\BackOffice\Invoicing\UpdateCreditNoteRequest;
use App\Support\Money;
use App\Traits\SearchesAccentInsensitively;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CreditNoteController extends Controller
{
    use SearchesAccentInsensitively;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', CreditNote::class);

        $search = $request->input('search');
        $status = $request->input('status');

        $query = CreditNote::query()->with(['invoice', 'billingCustomer']);

        if (in_array($status, array_column(CreditNoteStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        if (filled($search)) {
            $query->where(function (Builder $query) use ($search): void {
                $this->whereAccentInsensitiveLike($query, ['recipient_name'], $search);
                $query->orWhere('credit_note_number', 'like', "%{$search}%");
            });
        }

        return Inertia::render('backOffice/Invoicing/CreditNotes/Index', [
            'creditNotes' => $query->orderByDesc('created_at')->orderByDesc('id')->paginate(20)->withQueryString(),
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statuses' => array_map(
                fn (CreditNoteStatus $case): array => ['value' => $case->value, 'label' => $case->label()],
                CreditNoteStatus::cases(),
            ),
        ]);
    }

    public function create(Request $request, CreditNoteCreditLimit $creditLimit): Response
    {
        Gate::authorize('create', CreditNote::class);

        $validated = $request->validate([
            'invoice_id' => ['required', 'integer', Rule::exists('invoices', 'id')],
        ]);

        $invoice = Invoice::query()->with('items')->findOrFail((int) $validated['invoice_id']);

        return Inertia::render('backOffice/Invoicing/CreditNotes/Create', [
            'invoice' => $invoice,
            'remainingCreditableCents' => $creditLimit->remainingFor($invoice),
        ]);
    }

    public function store(StoreCreditNoteRequest $request, CreateCreditNote $create): RedirectResponse
    {
        $creditNote = $create->execute($request->validated(), $request->user());

        return redirect()
            ->route('bo.invoicing.credit-notes.show', $creditNote)
            ->with('success', 'Avoir brouillon créé.');
    }

    public function show(CreditNote $creditNote, CreditNoteBalanceCalculator $balances): Response
    {
        Gate::authorize('view', $creditNote);

        $creditNote->load([
            'invoice',
            'items.invoiceItem',
            'applications.invoice',
            'applications.reversals',
            'refunds',
            'reversals.items',
        ]);

        return Inertia::render('backOffice/Invoicing/CreditNotes/Show', [
            'creditNote' => $creditNote,
            'availableCents' => $balances->availableCents($creditNote),
            'appliedCents' => $balances->appliedCents($creditNote),
            'refundedCents' => $balances->refundedCents($creditNote),
            'reversedCents' => $balances->reversedCents($creditNote),
            'targetInvoices' => Invoice::query()
                ->where('billing_customer_id', $creditNote->billing_customer_id)
                ->whereIn('status', ['issued', 'partially_paid'])
                ->orderByDesc('issued_at')
                ->get(['id', 'invoice_number', 'recipient_name', 'total_cents', 'status']),
        ]);
    }

    public function edit(CreditNote $creditNote, CreditNoteCreditLimit $creditLimit): Response
    {
        Gate::authorize('update', $creditNote);

        $creditNote->load(['invoice', 'items']);

        return Inertia::render('backOffice/Invoicing/CreditNotes/Edit', [
            'creditNote' => $creditNote,
            'remainingCreditableCents' => $creditLimit->remainingFor($creditNote->invoice),
        ]);
    }

    public function update(
        UpdateCreditNoteRequest $request,
        CreditNote $creditNote,
        UpdateDraftCreditNote $update,
    ): RedirectResponse {
        $creditNote = $update->execute($creditNote, $request->validated(), $request->user());

        return redirect()
            ->route('bo.invoicing.credit-notes.show', $creditNote)
            ->with('success', 'Avoir brouillon mis à jour.');
    }

    public function destroy(CreditNote $creditNote, DeleteDraftCreditNote $delete): RedirectResponse
    {
        Gate::authorize('delete', $creditNote);

        $delete->execute($creditNote, request()->user());

        return redirect()
            ->route('bo.invoicing.credit-notes.index')
            ->with('success', 'Avoir brouillon supprimé.');
    }

    public function issue(
        IssueCreditNoteRequest $request,
        CreditNote $creditNote,
        IssueCreditNote $issue,
    ): RedirectResponse {
        try {
            $issued = $issue->execute($creditNote, $request->user());
        } catch (CreditNoteNotIssuable $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }

        return redirect()
            ->route('bo.invoicing.credit-notes.show', $issued)
            ->with('success', 'Avoir émis.');
    }

    public function preview(PreviewCreditNoteRequest $request, CreditNoteDraftService $draftService): JsonResponse
    {
        $prepared = $draftService->prepareItems($request->validated('items'));

        return response()->json([
            'lines' => array_map(fn (array $line): array => [
                'total_cents' => $line['total_cents'],
                'total' => Money::fromCents($line['total_cents']),
            ], $prepared['lines']),
            'subtotal_cents' => $prepared['subtotal_cents'],
            'subtotal' => Money::fromCents($prepared['subtotal_cents']),
            'total_cents' => $prepared['total_cents'],
            'total' => Money::fromCents($prepared['total_cents']),
        ]);
    }

    public function apply(
        ApplyCreditNoteRequest $request,
        CreditNote $creditNote,
        ApplyCreditNoteToInvoice $apply,
    ): RedirectResponse {
        $invoice = Invoice::query()->findOrFail((int) $request->validated('invoice_id'));

        try {
            $apply->execute(
                $creditNote,
                $invoice,
                Money::toCents($request->validated('amount')) ?? 0,
                $request->user(),
            );
        } catch (CreditNoteNotApplicable $exception) {
            return back()->withErrors(['amount' => $exception->getMessage()]);
        }

        return back()->with('success', 'Avoir imputé.');
    }

    public function reverseApplication(
        ReverseCreditNoteApplicationRequest $request,
        CreditNoteApplication $creditNoteApplication,
        ReverseCreditNoteApplication $reverse,
    ): RedirectResponse {
        try {
            $reverse->execute(
                $creditNoteApplication,
                Money::toCents($request->validated('amount')) ?? 0,
                $request->validated('reason'),
                $request->user(),
            );
        } catch (CreditNoteNotApplicable $exception) {
            return back()->withErrors(['amount' => $exception->getMessage()]);
        }

        return back()->with('success', 'Imputation annulée.');
    }

    public function refund(
        RecordCreditNoteRefundRequest $request,
        CreditNote $creditNote,
        RecordCreditNoteRefund $refund,
    ): RedirectResponse {
        $data = $request->validated();
        $data['amount_cents'] = Money::toCents($data['amount']) ?? 0;

        try {
            $refund->execute($creditNote, $data, $request->user());
        } catch (CreditNoteNotApplicable $exception) {
            return back()->withErrors(['amount' => $exception->getMessage()]);
        }

        return back()->with('success', 'Remboursement enregistré.');
    }

    public function createReversal(CreditNote $creditNote, CreditNoteBalanceCalculator $balances): Response|RedirectResponse
    {
        Gate::authorize('reverse', $creditNote);
        $creditNote->load('items');

        $available = $balances->availableCents($creditNote);

        if ($available <= 0 || $balances->appliedCents($creditNote) > 0 || $balances->refundedCents($creditNote) > 0) {
            return redirect()
                ->route('bo.invoicing.credit-notes.show', $creditNote)
                ->withErrors(['action' => "Annulez d'abord les imputations et remboursements avant de créer un avoir d'annulation."]);
        }

        return Inertia::render('backOffice/Invoicing/CreditNotes/CreateReversal', [
            'creditNote' => $creditNote,
            'availableCents' => $available,
        ]);
    }

    public function storeReversal(
        StoreCreditNoteReversalRequest $request,
        CreditNote $creditNote,
        CreateCreditNoteReversal $create,
    ): RedirectResponse {
        try {
            $create->execute($creditNote, $request->validated(), $request->user());
        } catch (CreditNoteNotApplicable $exception) {
            return back()->withErrors(['items' => $exception->getMessage()]);
        }

        return redirect()
            ->route('bo.invoicing.credit-notes.show', $creditNote)
            ->with('success', "Avoir d'annulation brouillon créé.");
    }

    public function issueReversal(
        IssueCreditNoteReversalRequest $request,
        CreditNoteReversal $creditNoteReversal,
        IssueCreditNoteReversal $issue,
    ): RedirectResponse {
        try {
            $issue->execute($creditNoteReversal, $request->user());
        } catch (CreditNoteReversalNotIssuable $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }

        return back()->with('success', "Avoir d'annulation émis.");
    }
}
