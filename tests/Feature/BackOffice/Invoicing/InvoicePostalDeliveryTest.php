<?php

namespace Tests\Feature\BackOffice\Invoicing;

use App\Domains\Invoicing\Enums\DeliveryStatus;
use App\Domains\Invoicing\Enums\FinancialDocumentStatus;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Models\InvoiceDelivery;
use App\Domains\Invoicing\Services\FinancialDocumentGenerator;
use App\Domains\Invoicing\Services\PdfFinancialDocumentGenerator;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Support\IssuesInvoices;
use Tests\TestCase;

class InvoicePostalDeliveryTest extends TestCase
{
    use IssuesInvoices;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(FinancialDocumentGenerator::class, PdfFinancialDocumentGenerator::class);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_generates_a_combined_pdf_and_marks_invoices_printed(): void
    {
        $first = $this->issueInvoice(InvoiceDeliveryMethod::Postal);
        $second = $this->issueInvoice(InvoiceDeliveryMethod::Postal);
        $firstHash = $first->document->sha256;
        $secondHash = $second->document->sha256;

        $response = $this->post(route('bo.invoicing.deliveries.postal.print'), [
            'invoice_ids' => [$first->id, $second->id],
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $pdf = $response->streamedContent();
        $this->assertStringStartsWith('%PDF', $pdf);

        $reader = new Fpdi;
        $this->assertSame(4, $reader->setSourceFile(StreamReader::createByString($pdf)));

        $deliveries = InvoiceDelivery::query()->orderBy('id')->get();
        $this->assertCount(2, $deliveries);
        $this->assertSame(1, $deliveries->pluck('batch_id')->unique()->count());
        $this->assertTrue($deliveries->every(fn (InvoiceDelivery $delivery): bool => $delivery->status === DeliveryStatus::Printed));
        $this->assertStringContainsString('Alice Martin', $deliveries[0]->recipient);
        $this->assertSame($firstHash, $first->document->fresh()->sha256);
        $this->assertSame($secondHash, $second->document->fresh()->sha256);
        $this->assertDatabaseHas('invoice_activity_logs', ['invoice_id' => $first->id, 'action' => 'invoice.printed']);
        $this->assertDatabaseHas('invoice_activity_logs', ['invoice_id' => $second->id, 'action' => 'invoice.printed']);
    }

    public function test_printing_uses_the_invoice_address_snapshot(): void
    {
        $invoice = $this->issueInvoice(InvoiceDeliveryMethod::Postal);
        $invoice->billingCustomer->update([
            'address' => 'Nouvelle adresse 10',
            'postal_code' => '2000',
            'city' => 'Neuchâtel',
        ]);

        $this->post(route('bo.invoicing.invoices.print', $invoice))->assertOk();

        $delivery = InvoiceDelivery::sole();
        $this->assertStringContainsString('Rue du Lac 4', $delivery->recipient);
        $this->assertStringNotContainsString('Nouvelle adresse 10', $delivery->recipient);
    }

    public function test_postal_queue_excludes_already_printed_invoices(): void
    {
        $awaiting = $this->issueInvoice(InvoiceDeliveryMethod::Postal);
        $printed = $this->issueInvoice(InvoiceDeliveryMethod::Postal);
        InvoiceDelivery::factory()->for($printed)->create([
            'channel' => InvoiceDeliveryMethod::Postal,
            'recipient' => 'Alice Martin, Rue du Lac 4, 1003 Lausanne, CH',
            'status' => DeliveryStatus::Printed,
            'processed_at' => now(),
            'processed_by' => User::firstOrFail()->id,
        ]);

        $this->get(route('bo.invoicing.deliveries.postal.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('backOffice/Invoicing/Deliveries/Postal')
                ->has('invoices.data', 1)
                ->where('invoices.data.0.id', $awaiting->id)
            );
    }

    public function test_individual_reprint_creates_a_new_printed_attempt(): void
    {
        $invoice = $this->issueInvoice(InvoiceDeliveryMethod::Postal);

        $this->post(route('bo.invoicing.invoices.print', $invoice))->assertOk();
        $this->post(route('bo.invoicing.invoices.print', $invoice))->assertOk();

        $this->assertSame(2, InvoiceDelivery::count());
        $this->assertSame(2, InvoiceDelivery::where('status', DeliveryStatus::Printed)->count());
    }

    public function test_rejects_printing_when_pdf_is_not_stored(): void
    {
        $invoice = $this->issueInvoice(InvoiceDeliveryMethod::Postal);
        $invoice->document->update(['status' => FinancialDocumentStatus::Failed]);

        $response = $this->postJson(route('bo.invoicing.deliveries.postal.print'), [
            'invoice_ids' => [$invoice->id],
        ]);

        $response->assertUnprocessable()->assertJson([
            'message' => 'Le PDF archivé de cette facture est introuvable.',
        ]);
        $this->assertDatabaseCount('invoice_deliveries', 0);
    }

    public function test_rejects_printing_a_cancelled_invoice(): void
    {
        $invoice = $this->issueInvoice(InvoiceDeliveryMethod::Postal);
        $invoice->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => User::firstOrFail()->id,
            'cancellation_reason' => 'Test',
        ]);

        $this->postJson(route('bo.invoicing.deliveries.postal.print'), [
            'invoice_ids' => [$invoice->id],
        ])->assertUnprocessable();
    }

    public function test_bulk_print_skips_invoices_already_printed(): void
    {
        $printed = $this->issueInvoice(InvoiceDeliveryMethod::Postal);
        $awaiting = $this->issueInvoice(InvoiceDeliveryMethod::Postal);
        $this->post(route('bo.invoicing.invoices.print', $printed))->assertOk();

        $this->postJson(route('bo.invoicing.deliveries.postal.print'), [
            'invoice_ids' => [$printed->id, $awaiting->id],
        ])->assertOk();

        $this->assertSame(1, InvoiceDelivery::where('invoice_id', $awaiting->id)->count());
        $this->assertSame(1, InvoiceDelivery::where('invoice_id', $printed->id)->count());
    }

    public function test_view_only_user_cannot_print_invoice(): void
    {
        $invoice = $this->issueInvoice(InvoiceDeliveryMethod::Postal);
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['access backoffice', 'view invoices']);

        $this->actingAs($viewer)
            ->post(route('bo.invoicing.invoices.print', $invoice))
            ->assertForbidden();
    }
}
