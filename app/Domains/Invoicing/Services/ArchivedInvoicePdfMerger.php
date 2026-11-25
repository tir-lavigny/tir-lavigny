<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Exceptions\InvoiceDeliveryException;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

class ArchivedInvoicePdfMerger
{
    /**
     * @param  iterable<int, Invoice>  $invoices
     */
    public function merge(iterable $invoices): string
    {
        $pdf = new Fpdi('P', 'mm', 'A4');
        $hasPages = false;

        foreach ($invoices as $invoice) {
            $document = $invoice->document;

            if (! $document?->isStored()) {
                throw InvoiceDeliveryException::documentUnavailable();
            }

            $disk = Storage::disk($document->disk);

            if (! $disk->exists($document->path)) {
                throw InvoiceDeliveryException::documentUnavailable();
            }

            $source = $disk->get($document->path);

            if (! is_string($source) || $source === '') {
                throw InvoiceDeliveryException::documentUnavailable();
            }

            $pageCount = $pdf->setSourceFile(StreamReader::createByString($source));

            for ($page = 1; $page <= $pageCount; $page++) {
                $template = $pdf->importPage($page);
                $pdf->AddPage();
                $pdf->useTemplate($template);
                $hasPages = true;
            }
        }

        if (! $hasPages) {
            throw InvoiceDeliveryException::noInvoices();
        }

        return $pdf->Output('S');
    }
}
