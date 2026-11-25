<?php

namespace App\Domains\Invoicing\Services;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Sprain\SwissQrBill\PaymentPart\Output\FpdfOutput\FpdfOutput;
use Sprain\SwissQrBill\QrBill;

class SwissQrBillPdfAppender
{
    public function append(string $pdf, QrBill $qrBill): string
    {
        $document = new Fpdi('P', 'mm', 'A4');
        $pageCount = $document->setSourceFile(StreamReader::createByString($pdf));

        for ($page = 1; $page <= $pageCount; $page++) {
            $template = $document->importPage($page);
            $document->AddPage();
            $document->useTemplate($template);
        }

        $document->AddPage('P', 'A4');
        (new FpdfOutput($qrBill, 'fr', $document))->getPaymentPart();

        return $document->Output('S');
    }
}
