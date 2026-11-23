<?php

namespace Tests\Unit\Models;

use App\Domains\Invoicing\Enums\BillingCustomerType;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\Invoice;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_casts_type_and_delivery_method_to_enums(): void
    {
        $billingCustomer = BillingCustomer::factory()->create([
            'type' => BillingCustomerType::Organization,
            'preferred_invoice_delivery' => InvoiceDeliveryMethod::Postal,
        ]);

        $this->assertSame(BillingCustomerType::Organization, $billingCustomer->type);
        $this->assertSame(InvoiceDeliveryMethod::Postal, $billingCustomer->preferred_invoice_delivery);
    }

    public function test_belongs_to_member_and_exposes_member_billing_customer(): void
    {
        $member = Member::factory()->create();
        $billingCustomer = BillingCustomer::factory()->forMember($member)->create();

        $this->assertTrue($billingCustomer->member->is($member));
        $this->assertTrue($member->billingCustomer->is($billingCustomer));
    }

    public function test_has_many_invoices(): void
    {
        $billingCustomer = BillingCustomer::factory()->create();
        $invoice = Invoice::factory()->create(['billing_customer_id' => $billingCustomer->id]);

        $this->assertTrue($billingCustomer->invoices->first()->is($invoice));
    }

    public function test_display_name_uses_organization_name_for_organizations(): void
    {
        $billingCustomer = BillingCustomer::factory()->organization()->create([
            'organization_name' => 'Tir Sportif SA',
        ]);

        $this->assertSame('Tir Sportif SA', $billingCustomer->displayName());
    }

    public function test_display_name_uses_first_and_last_name_for_individuals(): void
    {
        $billingCustomer = BillingCustomer::factory()->create([
            'first_name' => 'Alice',
            'last_name' => 'Martin',
        ]);

        $this->assertSame('Alice Martin', $billingCustomer->displayName());
    }
}
