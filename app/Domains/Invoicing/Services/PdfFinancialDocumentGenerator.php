<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CreditNoteReversal;
use App\Domains\Invoicing\Models\Invoice;
use Spatie\LaravelPdf\Facades\Pdf;

class PdfFinancialDocumentGenerator implements FinancialDocumentGenerator
{
    public function __construct(
        private readonly SwissQrBillFactory $qrBills,
        private readonly SwissQrBillPdfAppender $qrBillAppender,
        private readonly InvoicingSettingsService $settings,
    ) {}

    public function invoice(Invoice $invoice): string
    {
        $content = Pdf::view('pdfs.invoice', [
            'invoice' => $invoice,
            'envelopeWindow' => $this->settings->envelopeWindow()->value,
        ])
            ->driver('dompdf')
            ->format('a4')
            ->generatePdfContent();

        return $this->qrBillAppender->append($content, $this->qrBills->forInvoice($invoice));
    }

    public function creditNote(CreditNote $creditNote): string
    {
        return Pdf::view('pdfs.credit-note', [
            'creditNote' => $creditNote,
            'envelopeWindow' => $this->settings->envelopeWindow()->value,
        ])
            ->driver('dompdf')
            ->format('a4')
            ->generatePdfContent();
    }

    public function creditNoteReversal(CreditNoteReversal $reversal): string
    {
        return Pdf::view('pdfs.credit-note-reversal', [
            'reversal' => $reversal,
            'envelopeWindow' => $this->settings->envelopeWindow()->value,
        ])
            ->driver('dompdf')
            ->format('a4')
            ->generatePdfContent();
    }
}
