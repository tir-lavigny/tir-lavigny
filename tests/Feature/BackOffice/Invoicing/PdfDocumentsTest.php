<?php

namespace Tests\Feature\BackOffice\Invoicing;

use App\Domains\Invoicing\Actions\Invoices\CreateInvoice;
use App\Domains\Invoicing\Actions\Invoices\IssueInvoice;
use App\Domains\Invoicing\Enums\FinancialDocumentStatus;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceItem;
use App\Domains\Invoicing\Services\FinancialDocumentArchiver;
use App\Domains\Invoicing\Services\FinancialDocumentGenerator;
use App\Domains\Invoicing\Services\PaymentReferenceGenerator;
use App\Domains\Invoicing\Services\PdfFinancialDocumentGenerator;
use App\Domains\Invoicing\Services\SwissQrBillFactory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class PdfDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(FinancialDocumentGenerator::class, PdfFinancialDocumentGenerator::class);
    }

    public function test_issues_an_invoice_with_a_valid_qr_bill_and_archived_document(): void
    {
        $actor = User::factory()->create();
        $customer = BillingCustomer::factory()->create();
        $invoice = app(CreateInvoice::class)->execute($this->invoiceData($customer->id), $actor);

        $issued = app(IssueInvoice::class)->execute($invoice, $actor);

        $this->assertNotNull($issued->document);
        $this->assertSame(FinancialDocumentStatus::Stored, $issued->document->status);
        $this->assertNotNull($issued->payment_reference);
        $this->assertSame('CH9300762011623852957', $issued->creditor_iban);
        $this->assertSame('invoices/2027/'.$issued->invoice_number.'.pdf', $issued->document->path);
    }

    public function test_generates_and_archives_an_invoice_pdf_with_qr_bill_page(): void
    {
        $invoice = $this->issuedInvoice();
        $generator = app(FinancialDocumentGenerator::class);
        $archiver = app(FinancialDocumentArchiver::class);

        $pdf = $generator->invoice($invoice);
        $document = $archiver->queue($invoice, $pdf, 'invoices', "{$invoice->invoice_number}.pdf");
        $archiver->store($document->id, $pdf);
        $document->refresh();

        $this->assertSame(FinancialDocumentStatus::Stored, $document->status);
        $this->assertTrue(Storage::disk($document->disk)->exists($document->path));
        $this->assertSame(hash('sha256', $pdf), $document->sha256);
        $this->assertStringStartsWith('%PDF', $pdf);

        $pdfReader = new Fpdi;
        $pageCount = $pdfReader->setSourceFile(Storage::disk($document->disk)->path($document->path));

        $this->assertSame(2, $pageCount);
    }

    public function test_qr_bill_uses_scor_reference_and_human_readable_information(): void
    {
        $invoice = $this->issuedInvoice();
        $qrBill = app(SwissQrBillFactory::class)->forInvoice($invoice);

        $this->assertCount(0, $qrBill->getViolations());
        $this->assertSame('SCOR', $qrBill->getPaymentReference()?->getType());
        $this->assertStringStartsWith('RF', (string) $qrBill->getPaymentReference()?->getReference());
        $this->assertSame("Facture {$invoice->invoice_number}", $qrBill->getAdditionalInformation()?->getFormattedString());
    }

    private function issuedInvoice(): Invoice
    {
        $invoice = Invoice::factory()->create([
            'status' => 'draft',
            'invoice_number' => null,
            'issue_date' => '2027-01-15',
            'due_date' => '2027-02-14',
            'subtotal_cents' => 15000,
            'total_cents' => 15000,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Cotisation',
            'quantity' => 1,
            'unit_price_cents' => 15000,
            'total_cents' => 15000,
            'position' => 1,
        ]);

        $invoice->update([
            'status' => 'issued',
            'invoice_number' => '2027-0001',
            'issued_at' => now(),
            'payment_reference' => app(PaymentReferenceGenerator::class)
                ->forInvoiceNumber('2027-0001'),
            'creditor_name' => 'Société de Tir à Lavigny',
            'creditor_address' => 'Rue du Stand 1',
            'creditor_postal_code' => '1175',
            'creditor_city' => 'Lavigny',
            'creditor_country' => 'CH',
            'creditor_iban' => 'CH9300762011623852957',
        ]);

        return $invoice->load('items');
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceData(int $customerId): array
    {
        return [
            'billing_customer_id' => $customerId,
            'delivery_method' => 'email',
            'issue_date' => '2027-01-15',
            'due_date' => '2027-02-14',
            'items' => [
                ['type' => 'fee', 'description' => 'Cotisation', 'quantity' => '1', 'unit_price' => '150.00'],
            ],
        ];
    }
}
