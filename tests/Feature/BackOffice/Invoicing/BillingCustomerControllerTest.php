<?php

namespace Tests\Feature\BackOffice\Invoicing;

use App\Domains\Invoicing\Enums\BillingCustomerType;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\Invoice;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BillingCustomerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_index_displays_billing_customers(): void
    {
        $this->actingAsAdmin();
        $member = Member::factory()->create();
        $billingCustomer = BillingCustomer::factory()->forMember($member)->create();

        $response = $this->get(route('bo.invoicing.billing-customers.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('backOffice/Invoicing/BillingCustomers/Index')
            ->has('billingCustomers.data', 1)
            ->where('billingCustomers.data.0.id', $billingCustomer->id)
            ->where('billingCustomers.data.0.member.id', $member->id)
        );
    }

    public function test_index_can_search_organization_without_accents(): void
    {
        $this->actingAsAdmin();
        BillingCustomer::factory()->organization()->create(['organization_name' => 'Société de Tir']);
        BillingCustomer::factory()->organization()->create(['organization_name' => 'Armurerie du Lac']);

        $response = $this->get(route('bo.invoicing.billing-customers.index', ['search' => 'societe']));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('billingCustomers.data', 1)
            ->where('billingCustomers.data.0.organization_name', 'Société de Tir')
        );
    }

    public function test_create_page_is_rendered(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('bo.invoicing.billing-customers.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('backOffice/Invoicing/BillingCustomers/Create')
            ->has('types', 2)
            ->has('deliveryMethods', 2)
        );
    }

    public function test_creates_an_individual_billing_customer(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('bo.invoicing.billing-customers.store'), [
            'type' => 'individual',
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'organization_name' => '',
            'address' => 'Rue du Lac 4',
            'postal_code' => '1003',
            'city' => 'Lausanne',
            'country' => 'CH',
            'email' => 'alice@example.test',
            'phone' => '+41 21 000 00 00',
            'preferred_invoice_delivery' => 'email',
        ]);

        $billingCustomer = BillingCustomer::first();

        $response->assertRedirect(route('bo.invoicing.billing-customers.edit', $billingCustomer));
        $response->assertSessionHas('success');
        $this->assertSame(BillingCustomerType::Individual, $billingCustomer->type);
        $this->assertSame('Alice', $billingCustomer->first_name);
        $this->assertSame(InvoiceDeliveryMethod::Email, $billingCustomer->preferred_invoice_delivery);
        $this->assertNull($billingCustomer->member_id);
    }

    public function test_creates_an_organization_billing_customer(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('bo.invoicing.billing-customers.store'), [
            'type' => 'organization',
            'first_name' => '',
            'last_name' => '',
            'organization_name' => 'Tir Sportif SA',
            'address' => 'Route de la Plaine 1',
            'postal_code' => '1174',
            'city' => 'Pampigny',
            'country' => 'CH',
            'email' => '',
            'phone' => '',
            'preferred_invoice_delivery' => 'postal',
        ]);

        $billingCustomer = BillingCustomer::first();

        $response->assertRedirect(route('bo.invoicing.billing-customers.edit', $billingCustomer));
        $this->assertSame(BillingCustomerType::Organization, $billingCustomer->type);
        $this->assertSame('Tir Sportif SA', $billingCustomer->organization_name);
    }

    public function test_rejects_an_individual_without_names(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('bo.invoicing.billing-customers.store'), [
            'type' => 'individual',
            'first_name' => '',
            'last_name' => '',
            'address' => 'Rue du Lac 4',
            'postal_code' => '1003',
            'city' => 'Lausanne',
            'country' => 'CH',
            'preferred_invoice_delivery' => 'email',
        ]);

        $response->assertSessionHasErrors(['first_name', 'last_name']);
        $this->assertDatabaseCount('billing_customers', 0);
    }

    public function test_rejects_an_organization_without_name(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('bo.invoicing.billing-customers.store'), [
            'type' => 'organization',
            'organization_name' => '',
            'address' => 'Route de la Plaine 1',
            'postal_code' => '1174',
            'city' => 'Pampigny',
            'country' => 'CH',
            'preferred_invoice_delivery' => 'postal',
        ]);

        $response->assertSessionHasErrors('organization_name');
        $this->assertDatabaseCount('billing_customers', 0);
    }

    public function test_rejects_an_invalid_country_code(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('bo.invoicing.billing-customers.store'), [
            'type' => 'individual',
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'address' => 'Rue du Lac 4',
            'postal_code' => '1003',
            'city' => 'Lausanne',
            'country' => 'Suisse',
            'preferred_invoice_delivery' => 'email',
        ]);

        $response->assertSessionHasErrors('country');
    }

    public function test_updates_a_billing_customer(): void
    {
        $this->actingAsAdmin();
        $billingCustomer = BillingCustomer::factory()->create();

        $response = $this->put(route('bo.invoicing.billing-customers.update', $billingCustomer), [
            'type' => 'individual',
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'organization_name' => '',
            'address' => 'Rue du Lac 4',
            'postal_code' => '1003',
            'city' => 'Lausanne',
            'country' => 'CH',
            'email' => 'alice@example.test',
            'phone' => '',
            'preferred_invoice_delivery' => 'postal',
        ]);

        $response->assertRedirect(route('bo.invoicing.billing-customers.edit', $billingCustomer));
        $billingCustomer->refresh();
        $this->assertSame('Alice', $billingCustomer->first_name);
        $this->assertSame('Lausanne', $billingCustomer->city);
        $this->assertSame(InvoiceDeliveryMethod::Postal, $billingCustomer->preferred_invoice_delivery);
    }

    public function test_creates_billing_customer_from_member(): void
    {
        $this->actingAsAdmin();
        $member = Member::factory()->create([
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'city' => 'Lausanne',
        ]);

        $response = $this->post(route('bo.invoicing.billing-customers.sync-member'), [
            'member_id' => $member->id,
        ]);

        $billingCustomer = BillingCustomer::first();

        $response->assertRedirect(route('bo.invoicing.billing-customers.edit', $billingCustomer));
        $this->assertSame($member->id, $billingCustomer->member_id);
        $this->assertSame('Lausanne', $billingCustomer->city);
    }

    public function test_rejects_second_billing_customer_for_same_member(): void
    {
        $this->actingAsAdmin();
        $member = Member::factory()->create();
        BillingCustomer::factory()->forMember($member)->create();

        $response = $this->post(route('bo.invoicing.billing-customers.sync-member'), [
            'member_id' => $member->id,
        ]);

        $response->assertSessionHasErrors('member_id');
        $this->assertDatabaseCount('billing_customers', 1);
    }

    public function test_synchronizes_existing_member_billing_customer(): void
    {
        $this->actingAsAdmin();
        $member = Member::factory()->create(['city' => 'Lausanne']);
        $billingCustomer = BillingCustomer::factory()->forMember($member)->create(['city' => 'Lausanne']);
        $member->update(['city' => 'Morges']);

        $response = $this->post(route('bo.invoicing.billing-customers.sync', $billingCustomer));

        $response->assertSessionHas('success');
        $this->assertSame('Morges', $billingCustomer->fresh()->city);
    }

    public function test_deletes_a_billing_customer_without_invoices(): void
    {
        $this->actingAsAdmin();
        $billingCustomer = BillingCustomer::factory()->create();

        $response = $this->delete(route('bo.invoicing.billing-customers.destroy', $billingCustomer));

        $response->assertRedirect(route('bo.invoicing.billing-customers.index'));
        $this->assertModelMissing($billingCustomer);
    }

    public function test_does_not_delete_a_billing_customer_with_invoices(): void
    {
        $this->actingAsAdmin();
        $billingCustomer = BillingCustomer::factory()->create();
        Invoice::factory()->create(['billing_customer_id' => $billingCustomer->id]);

        $response = $this->delete(route('bo.invoicing.billing-customers.destroy', $billingCustomer));

        $response->assertSessionHas('error');
        $this->assertModelExists($billingCustomer);
    }

    public function test_user_without_invoicing_permission_cannot_access_billing_customers(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access backoffice');
        $this->actingAs($user);

        $this->get(route('bo.invoicing.billing-customers.index'))->assertForbidden();
        $this->post(route('bo.invoicing.billing-customers.store'), [])->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('bo.invoicing.billing-customers.index'))->assertRedirect('/login');
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        return $admin;
    }
}
