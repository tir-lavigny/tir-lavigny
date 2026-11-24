<?php

namespace Tests\Feature\BackOffice\Invoicing;

use App\Domains\Invoicing\Models\BillingCustomer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicingRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_receives_all_invoicing_permissions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertTrue($admin->can('view invoices'));
        $this->assertTrue($admin->can('create invoices'));
        $this->assertTrue($admin->can('issue invoices'));
        $this->assertTrue($admin->can('send invoices'));
        $this->assertTrue($admin->can('cancel invoices'));
        $this->assertTrue($admin->can('view payments'));
        $this->assertTrue($admin->can('create payments'));
        $this->assertTrue($admin->can('manage invoicing'));
        $this->assertTrue($admin->can('manage membership fees'));
        $this->assertTrue($admin->can('manage invoice reminders'));
    }

    public function test_treasurer_can_manage_billing_customers(): void
    {
        $treasurer = User::factory()->create();
        $treasurer->assignRole('treasurer');

        $this->actingAs($treasurer)
            ->get(route('bo.invoicing.billing-customers.index'))
            ->assertOk();
    }

    public function test_view_only_user_can_list_but_not_create_billing_customers(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['access backoffice', 'view invoices']);

        $this->actingAs($viewer)
            ->get(route('bo.invoicing.billing-customers.index'))
            ->assertOk();

        $billingCustomer = BillingCustomer::factory()->create();

        $this->actingAs($viewer)
            ->get(route('bo.invoicing.billing-customers.edit', $billingCustomer))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('bo.invoicing.billing-customers.store'), [])
            ->assertForbidden();
    }

    public function test_billing_customer_policy_matches_permissions(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('view invoices');

        $manager = User::factory()->create();
        $manager->givePermissionTo('manage invoicing');

        $billingCustomer = BillingCustomer::factory()->create();

        $this->assertTrue($viewer->can('viewAny', BillingCustomer::class));
        $this->assertTrue($viewer->can('view', $billingCustomer));
        $this->assertFalse($viewer->can('create', BillingCustomer::class));
        $this->assertFalse($viewer->can('update', $billingCustomer));
        $this->assertFalse($viewer->can('delete', $billingCustomer));

        $this->assertTrue($manager->can('viewAny', BillingCustomer::class));
        $this->assertTrue($manager->can('view', $billingCustomer));
        $this->assertTrue($manager->can('create', BillingCustomer::class));
        $this->assertTrue($manager->can('update', $billingCustomer));
        $this->assertTrue($manager->can('delete', $billingCustomer));
    }

    public function test_user_without_invoicing_permissions_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access backoffice');

        $billingCustomer = BillingCustomer::factory()->create();

        $this->assertFalse($user->can('viewAny', BillingCustomer::class));
        $this->assertFalse($user->can('view', $billingCustomer));

        $this->actingAs($user)
            ->get(route('bo.invoicing.billing-customers.index'))
            ->assertForbidden();
    }
}
