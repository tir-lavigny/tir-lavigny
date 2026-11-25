<?php

namespace Tests\Feature\BackOffice\Invoicing;

use App\Domains\Invoicing\Enums\DeliveryStatus;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Models\InvoiceDelivery;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\IssuesInvoices;
use Tests\TestCase;

class InvoiceDeliveryMethodTest extends TestCase
{
    use IssuesInvoices;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_changes_delivery_method_before_distribution(): void
    {
        $invoice = $this->issueInvoice();

        $this->put(route('bo.invoicing.invoices.delivery-method', $invoice), [
            'delivery_method' => InvoiceDeliveryMethod::Postal->value,
        ])->assertRedirect();

        $this->assertSame(InvoiceDeliveryMethod::Postal, $invoice->fresh()->delivery_method);
        $this->assertDatabaseHas('invoice_activity_logs', [
            'invoice_id' => $invoice->id,
            'action' => 'invoice.delivery_method_changed',
        ]);
    }

    public function test_rejects_changing_method_after_successful_delivery(): void
    {
        $invoice = $this->issueInvoice();
        InvoiceDelivery::factory()->for($invoice)->create([
            'channel' => InvoiceDeliveryMethod::Email,
            'recipient' => $invoice->recipient_email,
            'status' => DeliveryStatus::Sent,
            'processed_at' => now(),
            'processed_by' => User::firstOrFail()->id,
        ]);

        $this->put(route('bo.invoicing.invoices.delivery-method', $invoice), [
            'delivery_method' => InvoiceDeliveryMethod::Postal->value,
        ])->assertSessionHasErrors('action');

        $this->assertSame(InvoiceDeliveryMethod::Email, $invoice->fresh()->delivery_method);
    }

    public function test_rejects_switching_to_email_without_email_snapshot(): void
    {
        $invoice = $this->issueInvoice(InvoiceDeliveryMethod::Postal);

        $this->put(route('bo.invoicing.invoices.delivery-method', $invoice), [
            'delivery_method' => InvoiceDeliveryMethod::Email->value,
        ])->assertSessionHasErrors('action');

        $this->assertSame(InvoiceDeliveryMethod::Postal, $invoice->fresh()->delivery_method);
    }

    public function test_view_only_user_cannot_change_delivery_method(): void
    {
        $invoice = $this->issueInvoice();
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['access backoffice', 'view invoices']);

        $this->actingAs($viewer)
            ->put(route('bo.invoicing.invoices.delivery-method', $invoice), [
                'delivery_method' => InvoiceDeliveryMethod::Postal->value,
            ])
            ->assertForbidden();
    }
}
