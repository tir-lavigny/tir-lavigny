<?php

namespace App\Domains\Invoicing\Services;

use Sprain\SwissQrBill\Reference\RfCreditorReferenceGenerator;

class PaymentReferenceGenerator
{
    public function forInvoiceNumber(string $invoiceNumber): string
    {
        $base = preg_replace('/[^A-Za-z0-9]/', '', $invoiceNumber) ?? '';

        return RfCreditorReferenceGenerator::generate($base);
    }
}
