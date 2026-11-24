<?php

namespace Tests\Feature\BackOffice\Invoicing;

use App\Domains\Invoicing\Actions\Invoices\CreateInvoice;
use App\Domains\Invoicing\Actions\Invoices\IssueInvoice;
use App\Domains\Invoicing\Enums\FinancialDocumentStatus;
use App\Domains\Invoicing\Exceptions\InvoiceNotIssuable;
use App\Domains\Invoicing\Exceptions\InvoicingSettingsMissing;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CreditNoteReversal;
use App\Domains\Invoicing\Models\FinancialDocument;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoicingSettings;
use App\Domains\Invoicing\Services\FinancialDocumentArchiver;
use App\Domains\Invoicing\Services\FinancialDocumentGenerator;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class FinancialDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_document_failed_when_storage_write_fails(): void
    {
        $invoice = Invoice::factory()->create();
        $document = FinancialDocument::create([
            'documentable_type' => $invoice->getMorphClass(),
            'documentable_id' => $invoice->id,
            'status' => FinancialDocumentStatus::Pending,
            'disk' => 'missing-disk',
            'path' => 'invoices/2027/2027-0001.pdf',
            'sha256' => hash('sha256', 'pdf'),
            'generated_at' => now(),
        ]);

        app(FinancialDocumentArchiver::class)->store($document->id, '%PDF-1.4 fake');

        $document->refresh();

        $this->assertSame(FinancialDocumentStatus::Failed, $document->status);
        $this->assertNotNull($document->error);
    }

    public function test_retry_regenerates_and_stores_a_failed_document(): void
    {
        $invoice = Invoice::factory()->create();
        $document = FinancialDocument::create([
            'documentable_type' => $invoice->getMorphClass(),
            'documentable_id' => $invoice->id,
            'status' => FinancialDocumentStatus::Failed,
            'disk' => 'local',
            'path' => 'invoices/2027/2027-0001.pdf',
            'sha256' => hash('sha256', 'old'),
            'error' => 'Disk unavailable',
            'generated_at' => now(),
        ]);

        $document = app(FinancialDocumentArchiver::class)
            ->retry($document, app(FinancialDocumentGenerator::class));

        $this->assertSame(FinancialDocumentStatus::Stored, $document->status);
        $this->assertNull($document->error);
        $this->assertNotNull($document->archived_at);
    }

    public function test_manager_can_retry_a_failed_document(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $invoice = Invoice::factory()->create();
        $document = FinancialDocument::create([
            'documentable_type' => $invoice->getMorphClass(),
            'documentable_id' => $invoice->id,
            'status' => FinancialDocumentStatus::Failed,
            'disk' => 'local',
            'path' => 'invoices/2027/2027-0001.pdf',
            'sha256' => hash('sha256', 'old'),
            'error' => 'Disk unavailable',
            'generated_at' => now(),
        ]);
        $user = User::factory()->create();
        $user->givePermissionTo(['access backoffice', 'manage invoicing']);

        $this->actingAs($user)
            ->post(route('bo.invoicing.financial-documents.retry', $document))
            ->assertSessionHas('success');

        $this->assertSame(FinancialDocumentStatus::Stored, $document->fresh()->status);
    }

    public function test_regenerate_command_replaces_archived_pdf(): void
    {
        $invoice = Invoice::factory()->create();
        $document = FinancialDocument::create([
            'documentable_type' => $invoice->getMorphClass(),
            'documentable_id' => $invoice->id,
            'status' => FinancialDocumentStatus::Stored,
            'disk' => 'local',
            'path' => 'invoices/2027/2027-0001.pdf',
            'sha256' => hash('sha256', 'old'),
            'generated_at' => now(),
            'archived_at' => now(),
        ]);

        $this->artisan('invoicing:regenerate-documents')
            ->assertFailed();

        $this->artisan('invoicing:regenerate-documents', ['--force' => true])
            ->assertSuccessful();

        $this->assertSame(FinancialDocumentStatus::Stored, $document->fresh()->status);
    }

    public function test_pdf_generation_failure_rolls_back_issuance(): void
    {
        $actor = User::factory()->create();
        $customer = BillingCustomer::factory()->create();
        $invoice = app(CreateInvoice::class)->execute($this->invoiceData($customer->id), $actor);

        $this->app->instance(FinancialDocumentGenerator::class, new class implements FinancialDocumentGenerator
        {
            public function invoice(Invoice $invoice): string
            {
                throw new RuntimeException('Generator failed');
            }

            public function creditNote(CreditNote $creditNote): string
            {
                throw new RuntimeException('Generator failed');
            }

            public function creditNoteReversal(CreditNoteReversal $reversal): string
            {
                throw new RuntimeException('Generator failed');
            }
        });

        try {
            app(IssueInvoice::class)->execute($invoice, $actor);
            $this->fail('Issuance should have failed.');
        } catch (InvoiceNotIssuable $exception) {
            $this->assertStringContainsString('PDF', $exception->getMessage());
        }

        $this->assertSame('draft', $invoice->fresh()->status->value);
        $this->assertDatabaseCount('financial_documents', 0);
    }

    public function test_issuance_marks_document_failed_when_after_commit_storage_fails(): void
    {
        config(['invoicing.documents.disk' => 'missing-disk']);
        $actor = User::factory()->create();
        $customer = BillingCustomer::factory()->create();
        $invoice = app(CreateInvoice::class)->execute($this->invoiceData($customer->id), $actor);

        $issued = app(IssueInvoice::class)->execute($invoice, $actor);

        $this->assertSame('issued', $issued->status->value);
        $this->assertSame(FinancialDocumentStatus::Failed, $issued->document?->status);
        $this->assertNotNull($issued->document?->error);
    }

    public function test_issuance_is_blocked_when_settings_are_missing(): void
    {
        $actor = User::factory()->create();
        $customer = BillingCustomer::factory()->create();
        $invoice = app(CreateInvoice::class)->execute($this->invoiceData($customer->id), $actor);
        InvoicingSettings::query()->delete();

        $this->expectException(InvoicingSettingsMissing::class);

        app(IssueInvoice::class)->execute($invoice, $actor);
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
