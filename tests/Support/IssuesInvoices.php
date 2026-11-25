<?php

namespace Tests\Support;

use App\Domains\Invoicing\Actions\Invoices\CreateInvoice;
use App\Domains\Invoicing\Actions\Invoices\IssueInvoice;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\Invoice;
use App\Models\User;

trait IssuesInvoices
{
    protected function invoicingAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        return $admin;
    }

    /**
     * @param  array<string, mixed>  $customerOverrides
     * @param  array<string, mixed>  $invoiceOverrides
     */
    protected function issueInvoice(
        InvoiceDeliveryMethod $method = InvoiceDeliveryMethod::Email,
        array $customerOverrides = [],
        array $invoiceOverrides = [],
        ?User $actor = null,
    ): Invoice {
        $actor ??= $this->invoicingAdmin();

        $customer = BillingCustomer::factory()->create([
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'address' => 'Rue du Lac 4',
            'postal_code' => '1003',
            'city' => 'Lausanne',
            'country' => 'CH',
            'email' => $method === InvoiceDeliveryMethod::Email ? 'alice@example.test' : null,
            'preferred_invoice_delivery' => $method,
            ...$customerOverrides,
        ]);

        $invoice = app(CreateInvoice::class)->execute([
            'billing_customer_id' => $customer->id,
            'delivery_method' => $method->value,
            'issue_date' => '2027-01-15',
            'due_date' => '2027-02-14',
            'items' => [
                ['type' => 'fee', 'description' => 'Cotisation', 'quantity' => '1', 'unit_price' => '150.00'],
            ],
            ...$invoiceOverrides,
        ], $actor);

        return app(IssueInvoice::class)->execute($invoice, $actor);
    }
}
