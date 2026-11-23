<?php

namespace Tests\Unit\Actions\Customers;

use App\Domains\Invoicing\Actions\Customers\SyncMemberBillingCustomer;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Exceptions\CannotInvoiceAnonymizedMember;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncMemberBillingCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_billing_customer_from_member(): void
    {
        $member = Member::factory()->create([
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'email' => 'alice@example.test',
            'street' => 'Rue du Lac 4',
            'npa' => '1003',
            'city' => 'Lausanne',
            'country' => 'Suisse',
        ]);

        $billingCustomer = app(SyncMemberBillingCustomer::class)->execute($member);

        $this->assertModelExists($billingCustomer);
        $this->assertSame($member->id, $billingCustomer->member_id);
        $this->assertSame('Alice', $billingCustomer->first_name);
        $this->assertSame('Martin', $billingCustomer->last_name);
        $this->assertSame('Rue du Lac 4', $billingCustomer->address);
        $this->assertSame('1003', $billingCustomer->postal_code);
        $this->assertSame('Lausanne', $billingCustomer->city);
        $this->assertSame('CH', $billingCustomer->country);
        $this->assertSame(InvoiceDeliveryMethod::Email, $billingCustomer->preferred_invoice_delivery);
    }

    public function test_updates_existing_member_billing_customer_without_duplicating_it(): void
    {
        $member = Member::factory()->create(['email' => 'before@example.test']);
        $action = app(SyncMemberBillingCustomer::class);
        $billingCustomer = $action->execute($member);

        $member->update(['email' => 'after@example.test', 'city' => 'Morges']);

        $synced = $action->execute($member->fresh());

        $this->assertSame($billingCustomer->id, $synced->id);
        $this->assertSame(1, BillingCustomer::count());
        $this->assertSame('after@example.test', $synced->email);
        $this->assertSame('Morges', $synced->city);
    }

    public function test_keeps_preferred_delivery_method_on_resync(): void
    {
        $member = Member::factory()->create(['email' => 'member@example.test']);
        $action = app(SyncMemberBillingCustomer::class);
        $billingCustomer = $action->execute($member);
        $billingCustomer->update(['preferred_invoice_delivery' => InvoiceDeliveryMethod::Postal]);

        $synced = $action->execute($member->fresh());

        $this->assertSame(InvoiceDeliveryMethod::Postal, $synced->preferred_invoice_delivery);
    }

    public function test_rejects_anonymized_member(): void
    {
        $member = Member::factory()->create(['anonymized_at' => now()]);

        $this->expectException(CannotInvoiceAnonymizedMember::class);

        app(SyncMemberBillingCustomer::class)->execute($member);
    }
}
