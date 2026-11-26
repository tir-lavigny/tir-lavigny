<?php

namespace Tests\Feature\BackOffice\Invoicing;

use App\Domains\Invoicing\Enums\CreditNoteStatus;
use App\Domains\Invoicing\Enums\MembershipFeeCampaignStatus;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\MembershipFeeCampaign;
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

    public function test_invoice_policy_matches_permissions_and_status(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('view invoices');

        $manager = User::factory()->create();
        $manager->givePermissionTo([
            'create invoices',
            'issue invoices',
            'cancel invoices',
            'manage invoicing',
        ]);

        $draft = Invoice::factory()->create();
        $issued = Invoice::factory()->create(['status' => 'issued']);
        $postal = Invoice::factory()->create(['status' => 'issued', 'delivery_method' => 'postal']);

        $this->assertFalse($viewer->can('send', $issued));
        $this->assertFalse($viewer->can('print', $postal));
        $this->assertFalse($viewer->can('changeDeliveryMethod', $issued));

        $this->assertTrue($viewer->can('view', $draft));
        $this->assertFalse($viewer->can('create', Invoice::class));
        $this->assertFalse($viewer->can('update', $draft));

        $this->assertTrue($manager->can('view', $draft));
        $this->assertTrue($manager->can('create', Invoice::class));
        $this->assertTrue($manager->can('update', $draft));
        $this->assertTrue($manager->can('delete', $draft));
        $this->assertTrue($manager->can('issue', $draft));
        $this->assertFalse($manager->can('cancel', $draft));

        $this->assertFalse($manager->can('update', $issued));
        $this->assertFalse($manager->can('delete', $issued));
        $this->assertFalse($manager->can('issue', $issued));
        $this->assertTrue($manager->can('cancel', $issued));
        $this->assertTrue($manager->can('send', $issued));
        $this->assertTrue($manager->can('print', $postal));
        $this->assertTrue($manager->can('changeDeliveryMethod', $issued));
    }

    public function test_credit_note_policy_matches_permissions_and_status(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('view invoices');

        $creator = User::factory()->create();
        $creator->givePermissionTo('create invoices');

        $manager = User::factory()->create();
        $manager->givePermissionTo(['issue invoices', 'manage invoicing']);

        $draft = CreditNote::factory()->create(['status' => CreditNoteStatus::Draft]);
        $issued = CreditNote::factory()->create(['status' => CreditNoteStatus::Issued]);

        $this->assertTrue($viewer->can('viewAny', CreditNote::class));
        $this->assertTrue($viewer->can('view', $draft));
        $this->assertFalse($viewer->can('create', CreditNote::class));
        $this->assertFalse($viewer->can('update', $draft));
        $this->assertFalse($viewer->can('delete', $draft));
        $this->assertFalse($viewer->can('issue', $draft));
        $this->assertFalse($viewer->can('apply', $issued));
        $this->assertFalse($viewer->can('refund', $issued));
        $this->assertFalse($viewer->can('reverse', $issued));

        $this->assertTrue($creator->can('create', CreditNote::class));
        $this->assertTrue($creator->can('update', $draft));
        $this->assertTrue($creator->can('delete', $draft));
        $this->assertFalse($creator->can('issue', $draft));
        $this->assertFalse($creator->can('apply', $issued));

        $this->assertTrue($manager->can('create', CreditNote::class));
        $this->assertTrue($manager->can('update', $draft));
        $this->assertTrue($manager->can('delete', $draft));
        $this->assertTrue($manager->can('issue', $draft));
        $this->assertFalse($manager->can('update', $issued));
        $this->assertFalse($manager->can('delete', $issued));
        $this->assertFalse($manager->can('issue', $issued));
        $this->assertTrue($manager->can('apply', $issued));
        $this->assertTrue($manager->can('refund', $issued));
        $this->assertTrue($manager->can('reverse', $issued));
    }

    public function test_membership_fee_campaign_policy_matches_permissions_and_status(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('view invoices');

        $manager = User::factory()->create();
        $manager->givePermissionTo('manage membership fees');

        $issuer = User::factory()->create();
        $issuer->givePermissionTo(['manage membership fees', 'issue invoices']);

        $draft = MembershipFeeCampaign::factory()->create([
            'year' => 2027,
            'status' => MembershipFeeCampaignStatus::Draft,
        ]);
        $validated = MembershipFeeCampaign::factory()->create([
            'year' => 2028,
            'status' => MembershipFeeCampaignStatus::Validated,
        ]);
        $invoiced = MembershipFeeCampaign::factory()->create([
            'year' => 2029,
            'status' => MembershipFeeCampaignStatus::Invoiced,
        ]);

        $this->assertTrue($viewer->can('viewAny', MembershipFeeCampaign::class));
        $this->assertTrue($viewer->can('view', $draft));
        $this->assertFalse($viewer->can('create', MembershipFeeCampaign::class));
        $this->assertFalse($viewer->can('update', $draft));
        $this->assertFalse($viewer->can('preview', $draft));
        $this->assertFalse($viewer->can('validate', $draft));
        $this->assertFalse($viewer->can('generateInvoices', $validated));

        $this->assertTrue($manager->can('create', MembershipFeeCampaign::class));
        $this->assertTrue($manager->can('update', $draft));
        $this->assertTrue($manager->can('preview', $draft));
        $this->assertTrue($manager->can('validate', $draft));
        $this->assertFalse($manager->can('generateInvoices', $draft));
        $this->assertTrue($manager->can('generateInvoices', $validated));
        $this->assertFalse($manager->can('issueInvoices', $invoiced));

        $this->assertTrue($issuer->can('issueInvoices', $invoiced));
    }

    public function test_payment_viewer_can_list_but_cannot_register_payment(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['access backoffice', 'view payments']);

        $this->actingAs($viewer)
            ->get(route('bo.invoicing.payments.index'))
            ->assertOk();

        $this->actingAs($viewer)
            ->post(route('bo.invoicing.payments.store'), [])
            ->assertForbidden();
    }

    public function test_viewer_cannot_send_or_print_reminders(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['access backoffice', 'view invoices']);
        $invoice = Invoice::factory()->create([
            'status' => 'issued',
            'invoice_number' => '2027-0001',
            'delivery_method' => 'email',
            'due_date' => now()->subDay(),
        ]);

        $this->actingAs($viewer)
            ->post(route('bo.invoicing.invoices.reminders.email', $invoice))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('bo.invoicing.invoices.reminders.print', $invoice))
            ->assertForbidden();
    }
}
