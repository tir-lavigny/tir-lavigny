<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Models\Invoice;
use RuntimeException;
use Sprain\SwissQrBill\DataGroup\Element\AdditionalInformation;
use Sprain\SwissQrBill\DataGroup\Element\CreditorInformation;
use Sprain\SwissQrBill\DataGroup\Element\PaymentAmountInformation;
use Sprain\SwissQrBill\DataGroup\Element\PaymentReference;
use Sprain\SwissQrBill\DataGroup\Element\StructuredAddress;
use Sprain\SwissQrBill\QrBill;

class SwissQrBillFactory
{
    public function forInvoice(Invoice $invoice, ?int $amountCents = null, ?string $additionalInformation = null): QrBill
    {
        $qrBill = QrBill::create();

        $qrBill->setCreditor(StructuredAddress::createWithStreet(
            (string) $invoice->creditor_name,
            (string) $invoice->creditor_address,
            null,
            (string) $invoice->creditor_postal_code,
            (string) $invoice->creditor_city,
            (string) $invoice->creditor_country,
        ));

        $qrBill->setCreditorInformation(CreditorInformation::create((string) $invoice->creditor_iban));

        $qrBill->setUltimateDebtor(StructuredAddress::createWithStreet(
            $invoice->recipient_name,
            $invoice->recipient_address,
            null,
            $invoice->recipient_postal_code,
            $invoice->recipient_city,
            $invoice->recipient_country,
        ));

        $qrBill->setPaymentAmountInformation(PaymentAmountInformation::create(
            $invoice->currency->value,
            ($amountCents ?? $invoice->total_cents) / 100,
        ));

        $qrBill->setPaymentReference(PaymentReference::create(
            PaymentReference::TYPE_SCOR,
            (string) $invoice->payment_reference,
        ));

        $qrBill->setAdditionalInformation(AdditionalInformation::create(
            $additionalInformation ?? "Facture {$invoice->invoice_number}",
        ));

        $violations = $qrBill->getViolations();

        if ($violations->count() > 0) {
            $messages = [];

            foreach ($violations as $violation) {
                $messages[] = $violation->getMessage();
            }

            throw new RuntimeException('QR-bill invalide: '.implode(' ', $messages));
        }

        return $qrBill;
    }
}
