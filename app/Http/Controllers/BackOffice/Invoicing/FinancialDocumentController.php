<?php

namespace App\Http\Controllers\BackOffice\Invoicing;

use App\Domains\Invoicing\Enums\FinancialDocumentStatus;
use App\Domains\Invoicing\Models\FinancialDocument;
use App\Domains\Invoicing\Services\FinancialDocumentArchiver;
use App\Domains\Invoicing\Services\FinancialDocumentGenerator;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Throwable;

class FinancialDocumentController extends Controller
{
    public function retry(
        FinancialDocument $financialDocument,
        FinancialDocumentArchiver $archiver,
        FinancialDocumentGenerator $generator,
    ): RedirectResponse {
        abort_unless(request()->user()?->can('manage invoicing'), 403);

        try {
            $financialDocument = $archiver->retry($financialDocument, $generator);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['document' => 'Le PDF n’a pas pu être régénéré.']);
        }

        return $financialDocument->status === FinancialDocumentStatus::Stored
            ? back()->with('success', 'PDF archivé.')
            : back()->withErrors(['document' => $financialDocument->error ?? 'Le PDF n’a pas pu être archivé.']);
    }
}
