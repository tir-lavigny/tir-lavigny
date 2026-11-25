<?php

namespace App\Http\Controllers\BackOffice\Invoicing;

use App\Domains\Invoicing\Actions\Reminders\PrintInvoiceReminder;
use App\Domains\Invoicing\Actions\Reminders\SendInvoiceReminderEmail;
use App\Domains\Invoicing\Exceptions\InvoiceReminderException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoiceListQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceReminderController extends Controller
{
    public function index(Request $request, InvoiceListQuery $invoices): Response
    {
        Gate::authorize('viewAny', Invoice::class);

        $search = $request->input('search');
        $year = $request->integer('year') ?: null;

        $overdue = $invoices
            ->overdue([
                'search' => is_string($search) ? $search : null,
                'year' => $year,
            ])
            ->with(['latestReminder.processor'])
            ->withCount('reminders')
            ->orderBy('due_date')
            ->orderBy('invoice_number')
            ->paginate(20)
            ->withQueryString();

        $overdue->through(fn (Invoice $invoice): Invoice => $invoices->applyBalanceAttributes($invoice));

        return Inertia::render('backOffice/Invoicing/Reminders/Overdue', [
            'invoices' => $overdue,
            'filters' => [
                'search' => $search,
                'year' => $year,
            ],
            'years' => $this->yearOptions(),
            'canSendReminders' => $request->user()?->canAny(['manage invoice reminders', 'manage invoicing']) ?? false,
        ]);
    }

    public function sendEmail(
        Invoice $invoice,
        SendInvoiceReminderEmail $sendReminder,
    ): RedirectResponse {
        Gate::authorize('sendReminder', $invoice);

        try {
            $sendReminder->execute($invoice, request()->user());
        } catch (InvoiceReminderException $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }

        return back()->with('success', "Le rappel email de la facture {$invoice->invoice_number} a été mis en file d’attente.");
    }

    public function print(
        Invoice $invoice,
        PrintInvoiceReminder $printReminder,
    ): StreamedResponse|JsonResponse|RedirectResponse {
        Gate::authorize('printReminder', $invoice);

        try {
            $result = $printReminder->execute($invoice, request()->user());
        } catch (InvoiceReminderException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->streamDownload(
            fn () => print ($result['pdf']),
            'rappel-facture-'.($invoice->invoice_number ?? $invoice->id).'.pdf',
            ['Content-Type' => 'application/pdf'],
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
