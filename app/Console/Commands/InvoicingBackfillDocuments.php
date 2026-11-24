<?php

namespace App\Console\Commands;

use App\Domains\Invoicing\Enums\CreditNoteReversalStatus;
use App\Domains\Invoicing\Enums\CreditNoteStatus;
use App\Domains\Invoicing\Enums\FinancialDocumentStatus;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CreditNoteReversal;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\FinancialDocumentArchiver;
use App\Domains\Invoicing\Services\FinancialDocumentGenerator;
use App\Domains\Invoicing\Services\InvoicingSettingsService;
use App\Domains\Invoicing\Services\PaymentReferenceGenerator;
use Illuminate\Console\Command;
use Throwable;

class InvoicingBackfillDocuments extends Command
{
    protected $signature = 'invoicing:backfill-documents {--dry-run : Liste les documents sans les générer}';

    protected $description = 'Génère et archive les PDF manquants pour les documents déjà émis';

    public function handle(
        FinancialDocumentArchiver $archiver,
        FinancialDocumentGenerator $generator,
        InvoicingSettingsService $settings,
        PaymentReferenceGenerator $references,
    ): int {
        $failures = 0;

        $invoices = Invoice::query()
            ->where('status', '!=', InvoiceStatus::Draft->value)
            ->whereDoesntHave('document')
            ->with('items')
            ->orderBy('id')
            ->get();

        foreach ($invoices as $invoice) {
            if ($this->option('dry-run')) {
                $this->line("Facture {$invoice->invoice_number}");

                continue;
            }

            try {
                $this->fillInvoiceSnapshot($invoice, $settings, $references);
                $document = $archiver->queue($invoice, $generator->invoice($invoice), 'invoices', "{$invoice->invoice_number}.pdf")->refresh();
                $failures += $this->report($document->id, $document->status, $document->error);
            } catch (Throwable $exception) {
                report($exception);
                $failures++;
                $this->error("Facture {$invoice->invoice_number}: {$exception->getMessage()}");
            }
        }

        $creditNotes = CreditNote::query()
            ->where('status', '!=', CreditNoteStatus::Draft->value)
            ->whereDoesntHave('document')
            ->with('items', 'invoice')
            ->orderBy('id')
            ->get();

        foreach ($creditNotes as $creditNote) {
            if ($this->option('dry-run')) {
                $this->line("Avoir {$creditNote->credit_note_number}");

                continue;
            }

            try {
                $this->fillCreditNoteSnapshot($creditNote, $settings);
                $document = $archiver->queue($creditNote, $generator->creditNote($creditNote), 'credit-notes', "{$creditNote->credit_note_number}.pdf")->refresh();
                $failures += $this->report($document->id, $document->status, $document->error);
            } catch (Throwable $exception) {
                report($exception);
                $failures++;
                $this->error("Avoir {$creditNote->credit_note_number}: {$exception->getMessage()}");
            }
        }

        $reversals = CreditNoteReversal::query()
            ->where('status', CreditNoteReversalStatus::Issued->value)
            ->whereDoesntHave('document')
            ->with('items', 'creditNote')
            ->orderBy('id')
            ->get();

        foreach ($reversals as $reversal) {
            if ($this->option('dry-run')) {
                $this->line("Avoir d’annulation {$reversal->credit_note_reversal_number}");

                continue;
            }

            try {
                $this->fillReversalSnapshot($reversal, $settings);
                $document = $archiver->queue($reversal, $generator->creditNoteReversal($reversal), 'credit-note-reversals', "{$reversal->credit_note_reversal_number}.pdf")->refresh();
                $failures += $this->report($document->id, $document->status, $document->error);
            } catch (Throwable $exception) {
                report($exception);
                $failures++;
                $this->error("Avoir d’annulation {$reversal->credit_note_reversal_number}: {$exception->getMessage()}");
            }
        }

        $this->info($failures === 0 ? 'Backfill terminé.' : "Backfill terminé avec {$failures} échec(s).");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function fillInvoiceSnapshot(Invoice $invoice, InvoicingSettingsService $settings, PaymentReferenceGenerator $references): void
    {
        if (blank($invoice->payment_reference)) {
            $invoice->payment_reference = $references->forInvoiceNumber((string) $invoice->invoice_number);
        }

        if (blank($invoice->creditor_name)) {
            foreach ($settings->creditorSnapshot() as $field => $value) {
                $invoice->{$field} = $value;
            }
        }

        $invoice->saveQuietly();
    }

    private function fillCreditNoteSnapshot(CreditNote $creditNote, InvoicingSettingsService $settings): void
    {
        if (filled($creditNote->creditor_name)) {
            return;
        }

        $snapshot = filled($creditNote->invoice->creditor_name)
            ? $creditNote->invoice->only([
                'creditor_name',
                'creditor_address',
                'creditor_postal_code',
                'creditor_city',
                'creditor_country',
                'creditor_iban',
            ])
            : $settings->creditorSnapshot();

        foreach ($snapshot as $field => $value) {
            $creditNote->{$field} = $value;
        }

        $creditNote->saveQuietly();
    }

    private function fillReversalSnapshot(CreditNoteReversal $reversal, InvoicingSettingsService $settings): void
    {
        if (filled($reversal->creditor_name)) {
            return;
        }

        $snapshot = filled($reversal->creditNote->creditor_name)
            ? $reversal->creditNote->only([
                'creditor_name',
                'creditor_address',
                'creditor_postal_code',
                'creditor_city',
                'creditor_country',
                'creditor_iban',
            ])
            : $settings->creditorSnapshot();

        foreach ($snapshot as $field => $value) {
            $reversal->{$field} = $value;
        }

        $reversal->saveQuietly();
    }

    private function report(int $id, FinancialDocumentStatus $status, ?string $error): int
    {
        if ($status === FinancialDocumentStatus::Stored) {
            $this->info("Document #{$id} archivé.");

            return 0;
        }

        $this->error("Document #{$id}: {$error}");

        return 1;
    }
}
