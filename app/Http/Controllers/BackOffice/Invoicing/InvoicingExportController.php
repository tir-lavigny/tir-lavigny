<?php

namespace App\Http\Controllers\BackOffice\Invoicing;

use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\MembershipFeeCampaign;
use App\Domains\Invoicing\Services\InvoiceExportService;
use App\Domains\Invoicing\Services\MembershipFeeCampaignExportService;
use App\Domains\Invoicing\Services\PaymentExportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\Invoicing\ExportCampaignMembersRequest;
use App\Http\Requests\BackOffice\Invoicing\ExportInvoicesRequest;
use App\Http\Requests\BackOffice\Invoicing\ExportPaymentsRequest;
use App\Support\SpreadsheetExporter;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoicingExportController extends Controller
{
    public function invoices(
        ExportInvoicesRequest $request,
        InvoiceExportService $export,
        SpreadsheetExporter $spreadsheet,
    ): StreamedResponse {
        Gate::authorize('viewAny', Invoice::class);

        $validated = $request->validated();
        $format = ($validated['format'] ?? null) === 'csv' ? 'csv' : 'xlsx';

        return $spreadsheet->download(
            'factures-'.now()->format('Y-m-d').'.'.$format,
            $format,
            $export->headers(),
            $export->rows($export->query([
                'search' => $validated['search'] ?? null,
                'status' => $validated['status'] ?? null,
                'type' => $validated['type'] ?? null,
                'year' => isset($validated['year']) ? (int) $validated['year'] : null,
                'overdue' => $request->boolean('overdue'),
            ])),
            $export->widths(),
        );
    }

    public function payments(
        ExportPaymentsRequest $request,
        PaymentExportService $export,
        SpreadsheetExporter $spreadsheet,
    ): StreamedResponse {
        $validated = $request->validated();
        $format = ($validated['format'] ?? null) === 'csv' ? 'csv' : 'xlsx';

        return $spreadsheet->download(
            'paiements-'.now()->format('Y-m-d').'.'.$format,
            $format,
            $export->headers(),
            $export->rows($export->query([
                'search' => $validated['search'] ?? null,
                'method' => $validated['method'] ?? null,
                'status' => $validated['status'] ?? null,
                'allocation_status' => $validated['allocation_status'] ?? null,
                'year' => isset($validated['year']) ? (int) $validated['year'] : null,
            ])),
            $export->widths(),
        );
    }

    public function membershipFeeCampaign(
        ExportCampaignMembersRequest $request,
        MembershipFeeCampaign $membershipFeeCampaign,
        MembershipFeeCampaignExportService $export,
        SpreadsheetExporter $spreadsheet,
    ): StreamedResponse {
        Gate::authorize('view', $membershipFeeCampaign);

        $validated = $request->validated();
        $format = ($validated['format'] ?? null) === 'csv' ? 'csv' : 'xlsx';

        return $spreadsheet->download(
            'campagne-cotisation-'.$membershipFeeCampaign->year.'-'.now()->format('Y-m-d').'.'.$format,
            $format,
            $export->headers(),
            $export->rows($export->query($membershipFeeCampaign, [
                'search' => $validated['search'] ?? null,
                'member_status' => $validated['member_status'] ?? null,
            ])),
            $export->widths(),
        );
    }
}
