<?php

namespace App\Http\Controllers\BackOffice\Invoicing;

use App\Domains\Invoicing\Actions\Deliveries\ChangeInvoiceDeliveryMethod;
use App\Domains\Invoicing\Actions\Deliveries\PrintInvoiceDocuments;
use App\Domains\Invoicing\Actions\Deliveries\SendInvoiceEmail;
use App\Domains\Invoicing\Enums\DeliveryStatus;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Exceptions\InvoiceDeliveryException;
use App\Domains\Invoicing\Models\Invoice;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\Invoicing\PrintInvoiceDocumentsRequest;
use App\Http\Requests\BackOffice\Invoicing\SendInvoiceEmailsRequest;
use App\Http\Requests\BackOffice\Invoicing\UpdateInvoiceDeliveryMethodRequest;
use App\Traits\SearchesAccentInsensitively;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceDeliveryController extends Controller
{
    use SearchesAccentInsensitively;

    public function emailQueue(Request $request): Response
    {
        Gate::authorize('viewAny', Invoice::class);

        return Inertia::render('backOffice/Invoicing/Deliveries/Email', [
            'invoices' => $this->queueQuery($request, InvoiceDeliveryMethod::Email)->paginate(20)->withQueryString(),
            'filters' => $this->filters($request),
            'years' => $this->yearOptions(),
        ]);
    }

    public function postalQueue(Request $request): Response
    {
        Gate::authorize('viewAny', Invoice::class);

        return Inertia::render('backOffice/Invoicing/Deliveries/Postal', [
            'invoices' => $this->queueQuery($request, InvoiceDeliveryMethod::Postal)->paginate(20)->withQueryString(),
            'filters' => $this->filters($request),
            'years' => $this->yearOptions(),
        ]);
    }

    public function sendEmail(Invoice $invoice, SendInvoiceEmail $send): RedirectResponse
    {
        Gate::authorize('send', $invoice);

        try {
            $send->execute($invoice, request()->user());
        } catch (InvoiceDeliveryException $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }

        return back()->with('success', "L’email de la facture {$invoice->invoice_number} a été mis en file d’attente.");
    }

    public function sendBulkEmail(SendInvoiceEmailsRequest $request, SendInvoiceEmail $send): RedirectResponse
    {
        $invoiceIds = $request->validated('invoice_ids');
        $batchId = (string) Str::uuid();
        $invoices = Invoice::query()
            ->whereIn('id', $invoiceIds)
            ->get()
            ->keyBy('id');

        $queued = 0;
        $errors = [];

        foreach ($invoiceIds as $invoiceId) {
            $invoice = $invoices->get((int) $invoiceId);

            if ($invoice === null) {
                $errors[] = 'Une facture sélectionnée est introuvable.';

                continue;
            }

            try {
                $send->execute($invoice, $request->user(), $batchId);
                $queued++;
            } catch (InvoiceDeliveryException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        if ($queued === 0) {
            return back()->withErrors(['action' => $errors[0] ?? 'Aucun email n’a pu être mis en file d’attente.']);
        }

        if ($errors !== []) {
            return back()->with(
                'warning',
                "{$queued} email(s) mis en file d’attente. ".count($errors).' facture(s) ignorée(s) : '.$errors[0],
            );
        }

        return back()->with('success', "{$queued} email(s) mis en file d’attente.");
    }

    public function printPostal(
        PrintInvoiceDocumentsRequest $request,
        PrintInvoiceDocuments $print,
    ): StreamedResponse|JsonResponse {
        try {
            $batch = $print->execute($request->validated('invoice_ids'), $request->user());
        } catch (InvoiceDeliveryException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return $this->pdfResponse($batch['pdf']);
    }

    public function printInvoice(Invoice $invoice, PrintInvoiceDocuments $print): StreamedResponse|JsonResponse
    {
        Gate::authorize('print', $invoice);

        try {
            $batch = $print->execute([$invoice->id], request()->user(), onlyUnprinted: false);
        } catch (InvoiceDeliveryException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return $this->pdfResponse($batch['pdf']);
    }

    public function updateDeliveryMethod(
        UpdateInvoiceDeliveryMethodRequest $request,
        Invoice $invoice,
        ChangeInvoiceDeliveryMethod $change,
    ): RedirectResponse {
        try {
            $change->execute(
                $invoice,
                InvoiceDeliveryMethod::from((string) $request->validated('delivery_method')),
                $request->user(),
            );
        } catch (InvoiceDeliveryException $exception) {
            return back()->withErrors(['action' => $exception->getMessage()]);
        }

        return back()->with('success', 'Mode de distribution mis à jour.');
    }

    /**
     * @return Builder<Invoice>
     */
    private function queueQuery(Request $request, InvoiceDeliveryMethod $channel): Builder
    {
        $query = Invoice::query()
            ->with(['document', 'latestDelivery.processor'])
            ->whereIn('status', [
                InvoiceStatus::Issued,
                InvoiceStatus::PartiallyPaid,
                InvoiceStatus::Paid,
            ])
            ->where('delivery_method', $channel);

        $terminalStatus = $channel === InvoiceDeliveryMethod::Email
            ? DeliveryStatus::Sent
            : DeliveryStatus::Printed;

        $query->whereDoesntHave('deliveries', function (Builder $query) use ($channel, $terminalStatus): void {
            $query->where('channel', $channel)->where('status', $terminalStatus);
        });

        $search = $request->input('search');

        if (filled($search)) {
            $query->where(function (Builder $query) use ($search): void {
                $this->whereAccentInsensitiveLike($query, ['recipient_name'], $search);
                $query->orWhere('invoice_number', 'like', "%{$search}%");
            });
        }

        $year = $request->integer('year') ?: null;

        if ($year !== null) {
            $query->whereYear('issue_date', $year);
        }

        return $query->orderBy('issue_date')->orderBy('invoice_number');
    }

    /**
     * @return array{search: string|null, year: int|null}
     */
    private function filters(Request $request): array
    {
        return [
            'search' => $request->input('search'),
            'year' => $request->integer('year') ?: null,
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

    private function pdfResponse(string $pdf): StreamedResponse
    {
        return response()->streamDownload(
            fn () => print ($pdf),
            'impression-postale-'.now()->format('Ymd-Hi').'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}
