<?php

namespace Tests\Unit\Actions\Customers;

use App\Domains\Invoicing\Actions\Customers\SyncMemberBillingCustomer;
use App\Domains\Invoicing\Actions\Customers\UpdateBillingCustomer;
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

    public function test_member_update_automatically_syncs_linked_billing_customer(): void
    {
        $member = Member::factory()->create([
            'street' => 'Rue du Lac 4',
            'npa' => '1003',
            'city' => 'Lausanne',
        ]);
        $billingCustomer = app(SyncMemberBillingCustomer::class)->execute($member);

        $member->update([
            'street' => 'Avenue de la Gare 10',
            'npa' => '2000',
            'city' => 'Neuchâtel',
        ]);

        $billingCustomer->refresh();

        $this->assertSame('Avenue de la Gare 10', $billingCustomer->address);
        $this->assertSame('2000', $billingCustomer->postal_code);
        $this->assertSame('Neuchâtel', $billingCustomer->city);
        $this->assertNotNull($billingCustomer->last_synced_at);
    }

    public function test_member_update_does_not_create_a_billing_customer(): void
    {
        $member = Member::factory()->create();

        $member->update(['city' => 'Morges']);

        $this->assertDatabaseCount('billing_customers', 0);
    }

    public function test_disabled_automatic_sync_keeps_billing_customer_unchanged(): void
    {
        $member = Member::factory()->create(['city' => 'Lausanne']);
        $billingCustomer = app(SyncMemberBillingCustomer::class)->execute($member);
        $billingCustomer->update(['sync_with_member' => false]);

        $member->update(['city' => 'Morges']);

        $this->assertSame('Lausanne', $billingCustomer->fresh()->city);
    }

    public function test_anonymized_member_is_not_automatically_synced(): void
    {
        $member = Member::factory()->create(['city' => 'Lausanne']);
        $billingCustomer = app(SyncMemberBillingCustomer::class)->execute($member);

        $member->update(['anonymized_at' => now(), 'city' => 'Morges']);

        $this->assertSame('Lausanne', $billingCustomer->fresh()->city);
    }

    public function test_automatic_sync_preserves_preferred_delivery_method(): void
    {
        $member = Member::factory()->create(['email' => 'member@example.test', 'city' => 'Lausanne']);
        $billingCustomer = app(SyncMemberBillingCustomer::class)->execute($member);
        $billingCustomer->update(['preferred_invoice_delivery' => InvoiceDeliveryMethod::Postal]);

        $member->update(['city' => 'Morges']);

        $billingCustomer->refresh();

        $this->assertSame('Morges', $billingCustomer->city);
        $this->assertSame(InvoiceDeliveryMethod::Postal, $billingCustomer->preferred_invoice_delivery);
    }

    public function test_updating_billing_customer_with_automatic_sync_restores_member_values(): void
    {
        $member = Member::factory()->create([
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'street' => 'Rue du Lac 4',
            'npa' => '1003',
            'city' => 'Lausanne',
        ]);
        $billingCustomer = app(SyncMemberBillingCustomer::class)->execute($member);

        $updated = app(UpdateBillingCustomer::class)->execute($billingCustomer, [
            'first_name' => 'Bob',
            'last_name' => 'Martin',
            'address' => 'Rue du Lac 4',
            'postal_code' => '1003',
            'city' => 'Morges',
            'country' => 'CH',
            'preferred_invoice_delivery' => InvoiceDeliveryMethod::Postal->value,
            'sync_with_member' => true,
        ]);

        $this->assertSame('Alice', $updated->first_name);
        $this->assertSame('Lausanne', $updated->city);
        $this->assertSame(InvoiceDeliveryMethod::Postal, $updated->preferred_invoice_delivery);
    }

    public function test_updating_billing_customer_does_not_change_member(): void
    {
        $member = Member::factory()->create(['first_name' => 'Alice', 'city' => 'Lausanne']);
        $billingCustomer = app(SyncMemberBillingCustomer::class)->execute($member);
        $billingCustomer->update(['sync_with_member' => false]);

        $updated = app(UpdateBillingCustomer::class)->execute($billingCustomer, [
            'first_name' => 'Bob',
            'city' => 'Morges',
        ]);

        $this->assertSame('Bob', $updated->first_name);
        $this->assertSame('Morges', $updated->city);
        $this->assertSame('Alice', $member->fresh()->first_name);
        $this->assertSame('Lausanne', $member->fresh()->city);
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
