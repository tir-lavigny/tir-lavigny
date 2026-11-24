<?php

namespace Tests\Feature\BackOffice\Invoicing;

use App\Domains\Invoicing\Actions\CreditNotes\CreateCreditNote;
use App\Domains\Invoicing\Actions\CreditNotes\CreateCreditNoteReversal;
use App\Domains\Invoicing\Actions\CreditNotes\IssueCreditNote;
use App\Domains\Invoicing\Actions\CreditNotes\IssueCreditNoteReversal;
use App\Domains\Invoicing\Actions\CreditNotes\ReverseCreditNoteApplication;
use App\Domains\Invoicing\Actions\Invoices\CreateInvoice;
use App\Domains\Invoicing\Actions\Invoices\IssueInvoice;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\Invoice;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinancialDocumentDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_authorized_user_downloads_the_archived_pdf(): void
    {
        $admin = $this->admin();
        $invoice = $this->issuedInvoice($admin);
        $document = $invoice->document;

        $this->assertNotNull($document);
        $stored = Storage::disk($document->disk)->get($document->path);

        $response = $this->actingAs($admin)
            ->get(route('bo.invoicing.invoices.pdf', $invoice))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertSame($stored, $response->streamedContent());
        $this->assertStringContainsString(
            "facture-{$invoice->invoice_number}.pdf",
            (string) $response->headers->get('Content-Disposition'),
        );
    }

    public function test_download_uses_archived_file_after_customer_change(): void
    {
        $admin = $this->admin();
        $invoice = $this->issuedInvoice($admin);
        $document = $invoice->document;
        $before = Storage::disk($document->disk)->get($document->path);

        $invoice->billingCustomer->update(['first_name' => 'Changed', 'last_name' => 'Customer']);

        $response = $this->actingAs($admin)
            ->get(route('bo.invoicing.invoices.pdf', $invoice))
            ->assertOk();

        $this->assertSame($before, $response->streamedContent());
    }

    public function test_user_without_permission_cannot_download(): void
    {
        $invoice = $this->issuedInvoice($this->admin());
        $user = User::factory()->create();
        $user->givePermissionTo('access backoffice');

        $this->actingAs($user)
            ->get(route('bo.invoicing.invoices.pdf', $invoice))
            ->assertForbidden();
    }

    public function test_missing_document_returns_not_found(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'issued', 'invoice_number' => '2027-9999']);

        $this->actingAs($this->admin())
            ->get(route('bo.invoicing.invoices.pdf', $invoice))
            ->assertNotFound();
    }

    public function test_credit_note_pdf_can_be_downloaded(): void
    {
        $admin = $this->admin();
        $invoice = $this->issuedInvoice($admin);
        $creditNote = app(CreateCreditNote::class)->execute([
            'invoice_id' => $invoice->id,
            'reason' => 'Correction',
            'issue_date' => '2027-01-20',
            'items' => [
                ['description' => 'Correction', 'quantity' => '1', 'unit_price' => '50.00'],
            ],
        ], $admin);

        $issued = app(IssueCreditNote::class)->execute($creditNote, $admin);
        $document = $issued->document;

        $this->assertNotNull($document);
        $this->assertSame('stored', $document->status->value);

        $this->actingAs($admin)
            ->get(route('bo.invoicing.credit-notes.pdf', $issued))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_credit_note_reversal_pdf_can_be_downloaded(): void
    {
        $admin = $this->admin();
        $invoice = $this->issuedInvoice($admin);
        $creditNote = app(CreateCreditNote::class)->execute([
            'invoice_id' => $invoice->id,
            'reason' => 'Correction',
            'issue_date' => '2027-01-20',
            'items' => [
                ['description' => 'Correction', 'quantity' => '1', 'unit_price' => '50.00'],
            ],
        ], $admin);
        $creditNote = app(IssueCreditNote::class)->execute($creditNote, $admin);
        $application = $creditNote->applications()->firstOrFail();
        app(ReverseCreditNoteApplication::class)->execute($application, 5000, 'Correction', $admin);
        $creditNote = CreditNote::query()->findOrFail($creditNote->id);

        $reversal = app(CreateCreditNoteReversal::class)->execute($creditNote, [
            'reason' => 'Annulation',
            'issue_date' => '2027-02-01',
            'items' => [
                ['description' => 'Annulation', 'amount' => '50.00'],
            ],
        ], $admin);
        $reversal = app(IssueCreditNoteReversal::class)->execute($reversal, $admin);

        $this->assertNotNull($reversal->document);
        $this->assertSame('stored', $reversal->document->status->value);

        $this->actingAs($admin)
            ->get(route('bo.invoicing.credit-note-reversals.pdf', $reversal))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    private function issuedInvoice(User $actor): Invoice
    {
        $customer = BillingCustomer::factory()->create();
        $invoice = app(CreateInvoice::class)->execute($this->invoiceData($customer->id), $actor);

        return app(IssueInvoice::class)->execute($invoice, $actor);
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

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }
}
