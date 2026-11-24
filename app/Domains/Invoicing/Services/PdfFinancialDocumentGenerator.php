<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CreditNoteReversal;
use App\Domains\Invoicing\Models\Invoice;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Spatie\LaravelPdf\Facades\Pdf;
use Sprain\SwissQrBill\PaymentPart\Output\FpdfOutput\FpdfOutput;
use Sprain\SwissQrBill\QrBill;

class PdfFinancialDocumentGenerator implements FinancialDocumentGenerator
{
    public function __construct(
        private readonly SwissQrBillFactory $qrBills,
    ) {}

    public function invoice(Invoice $invoice): string
    {
        $content = Pdf::view('pdfs.invoice', ['invoice' => $invoice])
            ->driver('dompdf')
            ->format('a4')
            ->generatePdfContent();

        return $this->appendQrBill($content, $this->qrBills->forInvoice($invoice));
    }

    public function creditNote(CreditNote $creditNote): string
    {
        return Pdf::view('pdfs.credit-note', ['creditNote' => $creditNote])
            ->driver('dompdf')
            ->format('a4')
            ->generatePdfContent();
    }

    public function creditNoteReversal(CreditNoteReversal $reversal): string
    {
        return Pdf::view('pdfs.credit-note-reversal', ['reversal' => $reversal])
            ->driver('dompdf')
            ->format('a4')
            ->generatePdfContent();
    }

    private function appendQrBill(string $invoicePdf, QrBill $qrBill): string
    {
        $pdf = new Fpdi('P', 'mm', 'A4');
        $source = StreamReader::createByString($invoicePdf);
        $pageCount = $pdf->setSourceFile($source);

        for ($page = 1; $page <= $pageCount; $page++) {
            $template = $pdf->importPage($page);
            $pdf->AddPage();
            $pdf->useTemplate($template);
        }

        $pdf->AddPage('P', 'A4');
        (new FpdfOutput($qrBill, 'fr', $pdf))->getPaymentPart();

        return $pdf->Output('S');
    }
}
