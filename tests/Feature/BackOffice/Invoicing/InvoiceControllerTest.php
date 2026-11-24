<?php

namespace Tests\Feature\BackOffice\Invoicing;

use App\Domains\Invoicing\Actions\Invoices\CreateInvoice;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\Invoice;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_index_lists_invoices_with_remaining_balance(): void
    {
        $this->actingAsAdmin();
        $invoice = Invoice::factory()->create(['recipient_name' => 'Alice Martin']);

        $response = $this->get(route('bo.invoicing.invoices.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('backOffice/Invoicing/Invoices/Index')
            ->has('invoices.data', 1)
            ->where('invoices.data.0.id', $invoice->id)
            ->where('invoices.data.0.remaining_cents', $invoice->total_cents)
        );
    }

    public function test_create_page_is_rendered(): void
    {
        $this->actingAsAdmin();
        BillingCustomer::factory()->create();

        $this->get(route('bo.invoicing.invoices.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('backOffice/Invoicing/Invoices/Create')
                ->has('billingCustomers', 1)
                ->has('deliveryMethods', 2)
            );
    }

    public function test_creates_a_draft_invoice(): void
    {
        $this->actingAsAdmin();
        $customer = BillingCustomer::factory()->create();

        $response = $this->post(route('bo.invoicing.invoices.store'), $this->invoiceData($customer->id));

        $invoice = Invoice::firstOrFail();
        $response->assertRedirect(route('bo.invoicing.invoices.show', $invoice));
        $this->assertSame('draft', $invoice->status->value);
        $this->assertNull($invoice->invoice_number);
        $this->assertSame(12000, $invoice->total_cents);
        $this->assertCount(2, $invoice->items);
    }

    public function test_rejects_email_delivery_when_customer_has_no_email(): void
    {
        $this->actingAsAdmin();
        $customer = BillingCustomer::factory()->create(['email' => null]);

        $response = $this->post(route('bo.invoicing.invoices.store'), $this->invoiceData($customer->id));

        $response->assertSessionHasErrors('delivery_method');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_rejects_issuing_an_email_invoice_when_customer_lost_their_email(): void
    {
        $admin = $this->actingAsAdmin();
        $customer = BillingCustomer::factory()->create(['email' => 'before@example.test']);
        $invoice = app(CreateInvoice::class)->execute($this->invoiceData($customer->id), $admin);
        $customer->update(['email' => null]);

        $response = $this->post(route('bo.invoicing.invoices.issue', $invoice));

        $response->assertSessionHasErrors('action');
        $this->assertSame('draft', $invoice->fresh()->status->value);
    }

    public function test_show_includes_financial_balance(): void
    {
        $this->actingAsAdmin();
        $invoice = Invoice::factory()->create(['total_cents' => 12000]);

        $this->get(route('bo.invoicing.invoices.show', $invoice))
            ->assertInertia(fn (Assert $page) => $page
                ->component('backOffice/Invoicing/Invoices/Show')
                ->where('balance.paid_cents', 0)
                ->where('balance.credited_cents', 0)
                ->where('balance.outstanding_cents', 12000)
            );
    }

    public function test_rejects_an_invoice_without_lines(): void
    {
        $this->actingAsAdmin();
        $customer = BillingCustomer::factory()->create();

        $response = $this->post(route('bo.invoicing.invoices.store'), [
            ...$this->invoiceData($customer->id),
            'items' => [],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_rejects_an_invoice_with_a_negative_total(): void
    {
        $this->actingAsAdmin();
        $customer = BillingCustomer::factory()->create();
        $data = $this->invoiceData($customer->id);
        $data['items'] = [
            ['type' => 'fee', 'description' => 'Frais', 'quantity' => '1', 'unit_price' => '10.00'],
            ['type' => 'discount', 'description' => 'Remise', 'quantity' => '1', 'unit_price' => '-20.00'],
        ];

        $response = $this->post(route('bo.invoicing.invoices.store'), $data);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_rejects_a_positive_discount_line(): void
    {
        $this->actingAsAdmin();
        $customer = BillingCustomer::factory()->create();
        $data = $this->invoiceData($customer->id);
        $data['items'][1]['unit_price'] = '30.00';

        $response = $this->post(route('bo.invoicing.invoices.store'), $data);

        $response->assertSessionHasErrors('items.1.unit_price');
    }

    public function test_updates_a_draft_invoice(): void
    {
        $this->actingAsAdmin();
        $customer = BillingCustomer::factory()->create();
        $this->post(route('bo.invoicing.invoices.store'), $this->invoiceData($customer->id));
        $invoice = Invoice::firstOrFail();

        $response = $this->put(route('bo.invoicing.invoices.update', $invoice), $this->invoiceData($customer->id, [
            ['type' => 'fee', 'description' => 'Nouvelle ligne', 'quantity' => '1', 'unit_price' => '200.00'],
        ]));

        $response->assertRedirect(route('bo.invoicing.invoices.show', $invoice));
        $this->assertSame(20000, $invoice->fresh()->total_cents);
    }

    public function test_issues_an_invoice(): void
    {
        $this->actingAsAdmin();
        $customer = BillingCustomer::factory()->create();
        $this->post(route('bo.invoicing.invoices.store'), $this->invoiceData($customer->id));
        $invoice = Invoice::firstOrFail();

        $response = $this->post(route('bo.invoicing.invoices.issue', $invoice));

        $response->assertRedirect(route('bo.invoicing.invoices.show', $invoice));
        $invoice->refresh();
        $this->assertSame('issued', $invoice->status->value);
        $this->assertSame('2027-0001', $invoice->invoice_number);
    }

    public function test_rejects_editing_an_issued_invoice(): void
    {
        $this->actingAsAdmin();
        $customer = BillingCustomer::factory()->create();
        $this->post(route('bo.invoicing.invoices.store'), $this->invoiceData($customer->id));
        $invoice = Invoice::firstOrFail();
        $this->post(route('bo.invoicing.invoices.issue', $invoice));

        $this->put(route('bo.invoicing.invoices.update', $invoice), $this->invoiceData($customer->id))
            ->assertForbidden();
    }

    public function test_cancels_an_issued_invoice_with_reason(): void
    {
        $this->actingAsAdmin();
        $customer = BillingCustomer::factory()->create();
        $this->post(route('bo.invoicing.invoices.store'), $this->invoiceData($customer->id));
        $invoice = Invoice::firstOrFail();
        $this->post(route('bo.invoicing.invoices.issue', $invoice));
        $number = $invoice->fresh()->invoice_number;

        $response = $this->post(route('bo.invoicing.invoices.cancel', $invoice), ['reason' => 'Client parti']);

        $response->assertRedirect(route('bo.invoicing.invoices.show', $invoice));
        $invoice->refresh();
        $this->assertSame('cancelled', $invoice->status->value);
        $this->assertSame($number, $invoice->invoice_number);
        $this->assertSame('Client parti', $invoice->cancellation_reason);
    }

    public function test_rejects_cancellation_without_reason(): void
    {
        $this->actingAsAdmin();
        $customer = BillingCustomer::factory()->create();
        $this->post(route('bo.invoicing.invoices.store'), $this->invoiceData($customer->id));
        $invoice = Invoice::firstOrFail();
        $this->post(route('bo.invoicing.invoices.issue', $invoice));

        $this->post(route('bo.invoicing.invoices.cancel', $invoice), ['reason' => ''])
            ->assertSessionHasErrors('reason');
    }

    public function test_deletes_a_draft_invoice(): void
    {
        $this->actingAsAdmin();
        $customer = BillingCustomer::factory()->create();
        $this->post(route('bo.invoicing.invoices.store'), $this->invoiceData($customer->id));
        $invoice = Invoice::firstOrFail();

        $this->delete(route('bo.invoicing.invoices.destroy', $invoice))
            ->assertRedirect(route('bo.invoicing.invoices.index'));

        $this->assertModelMissing($invoice);
    }

    public function test_previews_invoice_totals_with_server_calculation(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson(route('bo.invoicing.invoices.preview'), [
            'items' => $this->invoiceData(1)['items'],
        ]);

        $response->assertOk()
            ->assertJson([
                'subtotal_cents' => 15000,
                'discounts_cents' => -3000,
                'total_cents' => 12000,
                'total' => '120.00',
            ]);
    }

    public function test_view_only_user_cannot_create_or_edit_invoices(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['access backoffice', 'view invoices']);
        $invoice = Invoice::factory()->create();

        $this->actingAs($viewer)
            ->get(route('bo.invoicing.invoices.index'))
            ->assertOk();

        $this->actingAs($viewer)
            ->get(route('bo.invoicing.invoices.create'))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->get(route('bo.invoicing.invoices.edit', $invoice))
            ->assertForbidden();
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        return $admin;
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $items
     * @return array<string, mixed>
     */
    private function invoiceData(int $customerId, ?array $items = null): array
    {
        return [
            'billing_customer_id' => $customerId,
            'delivery_method' => 'email',
            'issue_date' => '2027-01-15',
            'due_date' => '2027-02-14',
            'items' => $items ?? [
                ['type' => 'fee', 'description' => 'Cotisation', 'quantity' => '1', 'unit_price' => '150.00'],
                ['type' => 'discount', 'description' => 'Remise', 'quantity' => '1', 'unit_price' => '-30.00'],
            ],
        ];
    }
}
