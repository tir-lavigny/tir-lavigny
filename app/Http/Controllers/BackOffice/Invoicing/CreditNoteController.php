<?php

namespace App\Http\Controllers\BackOffice\Invoicing;

use App\Domains\Invoicing\Actions\CreditNotes\CreateCreditNote;
use App\Domains\Invoicing\Actions\CreditNotes\DeleteDraftCreditNote;
use App\Domains\Invoicing\Actions\CreditNotes\IssueCreditNote;
use App\Domains\Invoicing\Actions\CreditNotes\UpdateDraftCreditNote;
use App\Domains\Invoicing\Enums\CreditNoteStatus;
use App\Domains\Invoicing\Exceptions\CreditNoteNotIssuable;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\CreditNoteBalanceCalculator;
use App\Domains\Invoicing\Services\CreditNoteCreditLimit;
use App\Domains\Invoicing\Services\CreditNoteDraftService;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\Invoicing\IssueCreditNoteRequest;
use App\Http\Requests\BackOffice\Invoicing\PreviewCreditNoteRequest;
use App\Http\Requests\BackOffice\Invoicing\StoreCreditNoteRequest;
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

        $creditNote->load(['invoice', 'items.invoiceItem', 'applications.invoice']);

        return Inertia::render('backOffice/Invoicing/CreditNotes/Show', [
            'creditNote' => $creditNote,
            'availableCents' => $balances->availableCents($creditNote),
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
            return back()->with('error', $exception->getMessage());
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
}
