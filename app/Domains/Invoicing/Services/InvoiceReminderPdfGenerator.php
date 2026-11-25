<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Models\InvoiceReminder;
use Spatie\LaravelPdf\Facades\Pdf;

class InvoiceReminderPdfGenerator
{
    public function __construct(
        private readonly SwissQrBillFactory $qrBills,
        private readonly SwissQrBillPdfAppender $qrBillAppender,
        private readonly InvoicingSettingsService $settings,
    ) {}

    public function generate(InvoiceReminder $reminder): string
    {
        $reminder->loadMissing('invoice');
        $invoice = $reminder->invoice;

        $content = Pdf::view('pdfs.invoice-reminder', [
            'invoice' => $invoice,
            'reminder' => $reminder,
            'envelopeWindow' => $this->settings->envelopeWindow()->value,
        ])
            ->driver('dompdf')
            ->format('a4')
            ->generatePdfContent();

        $qrBill = $this->qrBills->forInvoice(
            $invoice,
            $reminder->outstanding_amount_cents,
            "Rappel facture {$invoice->invoice_number}",
        );

        return $this->qrBillAppender->append($content, $qrBill);
    }
}
