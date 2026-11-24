<?php

namespace App\Http\Controllers\BackOffice\Invoicing;

use App\Domains\Invoicing\Models\InvoicingSettings;
use App\Domains\Invoicing\Services\InvoicingSettingsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackOffice\Invoicing\UpdateInvoicingSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InvoicingSettingsController extends Controller
{
    public function edit(InvoicingSettingsService $settings): Response
    {
        abort_unless(request()->user()?->can('manage invoicing'), 403);

        return Inertia::render('backOffice/Invoicing/Settings/Edit', [
            'settings' => $settings->current(),
            'configured' => $settings->isConfigured(),
        ]);
    }

    public function update(
        UpdateInvoicingSettingsRequest $request,
        InvoicingSettingsService $settings,
    ): RedirectResponse {
        InvoicingSettings::query()->updateOrCreate(
            ['id' => 1],
            [
                ...$request->validated(),
                'updated_by' => $request->user()->id,
            ],
        );

        return to_route('bo.invoicing.settings.edit')
            ->with('success', $settings->isConfigured()
                ? 'Paramètres de facturation enregistrés.'
                : 'Les paramètres de facturation sont incomplets.');
    }
}
