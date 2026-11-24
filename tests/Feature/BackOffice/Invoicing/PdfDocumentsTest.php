<?php

namespace Tests\Feature\BackOffice\Invoicing;

use App\Domains\Invoicing\Actions\CreditNotes\CreateCreditNote;
use App\Domains\Invoicing\Actions\CreditNotes\CreateCreditNoteReversal;
use App\Domains\Invoicing\Actions\CreditNotes\IssueCreditNote;
use App\Domains\Invoicing\Actions\CreditNotes\IssueCreditNoteReversal;
use App\Domains\Invoicing\Actions\CreditNotes\ReverseCreditNoteApplication;
use App\Domains\Invoicing\Actions\Invoices\CreateInvoice;
use App\Domains\Invoicing\Actions\Invoices\IssueInvoice;
use App\Domains\Invoicing\Enums\FinancialDocumentStatus;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceItem;
use App\Domains\Invoicing\Models\InvoicingSettings;
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

    public function test_pdf_view_omits_swiss_country_from_recipient_address(): void
    {
        $invoice = $this->issuedInvoice();
        $html = view('pdfs.invoice', [
            'invoice' => $invoice,
            'envelopeWindow' => 'left',
        ])->render();

        preg_match('/<div class="address-window.*?<\/div>/s', $html, $swissAddress);
        $this->assertStringNotContainsString('CH', strip_tags($swissAddress[0]));

        $invoice->recipient_country = 'FR';
        $html = view('pdfs.invoice', [
            'invoice' => $invoice,
            'envelopeWindow' => 'left',
        ])->render();

        preg_match('/<div class="address-window.*?<\/div>/s', $html, $foreignAddress);
        $this->assertStringContainsString('FR', strip_tags($foreignAddress[0]));
    }

    public function test_right_window_mirrors_recipient_and_creditor_blocks(): void
    {
        $invoice = $this->issuedInvoice();
        $html = view('pdfs.invoice', [
            'invoice' => $invoice,
            'envelopeWindow' => 'right',
        ])->render();

        $this->assertStringContainsString('address-window window-right', $html);
        $this->assertStringContainsString('brand-left', $html);
    }

    public function test_generates_pdf_for_right_window_setting(): void
    {
        InvoicingSettings::query()->update(['envelope_window' => 'right']);
        $invoice = $this->issuedInvoice();
        $pdf = app(FinancialDocumentGenerator::class)->invoice($invoice);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('brand-left', view('pdfs.invoice', [
            'invoice' => $invoice,
            'envelopeWindow' => 'right',
        ])->render());
    }

    public function test_generates_credit_note_and_reversal_pdf_documents(): void
    {
        $actor = User::factory()->create();
        $customer = BillingCustomer::factory()->create();
        $invoice = app(CreateInvoice::class)->execute($this->invoiceData($customer->id), $actor);
        $invoice = app(IssueInvoice::class)->execute($invoice, $actor);

        $creditNote = app(CreateCreditNote::class)->execute([
            'invoice_id' => $invoice->id,
            'reason' => 'Correction',
            'issue_date' => '2027-01-20',
            'items' => [
                ['description' => 'Correction', 'quantity' => '1', 'unit_price' => '50.00'],
            ],
        ], $actor);
        $creditNote = app(IssueCreditNote::class)->execute($creditNote, $actor);

        $this->assertSame(FinancialDocumentStatus::Stored, $creditNote->document?->status);
        $this->assertStringStartsWith(
            '%PDF',
            Storage::disk($creditNote->document->disk)->get($creditNote->document->path),
        );

        $application = $creditNote->applications()->firstOrFail();
        app(ReverseCreditNoteApplication::class)->execute($application, 5000, 'Correction', $actor);
        $creditNote = CreditNote::query()->findOrFail($creditNote->id);

        $reversal = app(CreateCreditNoteReversal::class)->execute($creditNote, [
            'reason' => 'Annulation',
            'issue_date' => '2027-02-01',
            'items' => [
                ['description' => 'Annulation', 'amount' => '50.00'],
            ],
        ], $actor);
        $reversal = app(IssueCreditNoteReversal::class)->execute($reversal, $actor);

        $this->assertSame(FinancialDocumentStatus::Stored, $reversal->document?->status);
        $this->assertStringStartsWith(
            '%PDF',
            Storage::disk($reversal->document->disk)->get($reversal->document->path),
        );
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
