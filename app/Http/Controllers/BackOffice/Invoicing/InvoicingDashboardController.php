<?php

namespace App\Http\Controllers\BackOffice\Invoicing;

use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoicingDashboardService;
use App\Domains\Invoicing\Services\InvoicingSettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InvoicingDashboardController extends Controller
{
    public function index(
        Request $request,
        InvoicingDashboardService $dashboard,
        InvoicingSettingsService $settings,
    ): Response {
        Gate::authorize('viewAny', Invoice::class);

        $year = $request->integer('year') ?: (int) now()->format('Y');

        return Inertia::render('backOffice/Invoicing/Dashboard', [
            'summary' => $dashboard->summary($year),
            'years' => $this->yearOptions(),
            'invoicingConfigured' => $settings->isConfigured(),
        ]);
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
