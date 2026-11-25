<?php

namespace Tests\Feature\BackOffice\Invoicing;

use App\Domains\Invoicing\Enums\CampaignMemberStatus;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\MembershipFeeCampaignStatus;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\MembershipFeeCampaign;
use App\Domains\Invoicing\Models\MembershipFeeCampaignMember;
use App\Domains\Invoicing\Models\MembershipFeeType as MembershipFeeTypeModel;
use App\MemberStatus;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MembershipFeeCampaignControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_index_lists_fee_types_and_campaigns(): void
    {
        $this->actingAsAdmin();
        $feeType = MembershipFeeTypeModel::factory()->create(['name' => 'Cotisation active']);
        $campaign = MembershipFeeCampaign::factory()->create(['name' => 'Cotisations 2027', 'year' => 2027]);

        $this->get(route('bo.invoicing.membership-fee-types.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('backOffice/Invoicing/MembershipFeeTypes/Index')
                ->where('feeTypes.data.0.id', $feeType->id)
            );

        $this->get(route('bo.invoicing.membership-fee-campaigns.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('backOffice/Invoicing/MembershipFeeCampaigns/Index')
                ->where('campaigns.data.0.id', $campaign->id)
            );
    }

    public function test_creates_a_membership_fee_type(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('bo.invoicing.membership-fee-types.store'), [
            'name' => 'Cotisation active',
            'member_status' => MemberStatus::ACTIVE->value,
            'description' => 'Cotisation annuelle',
            'amount' => '150.00',
        ]);

        $feeType = MembershipFeeTypeModel::sole();

        $response->assertRedirect(route('bo.invoicing.membership-fee-types.index'));
        $this->assertSame(15000, $feeType->amount_cents);
        $this->assertSame(MemberStatus::ACTIVE, $feeType->member_status);
    }

    public function test_rejects_a_second_fee_type_for_the_same_member_status(): void
    {
        $this->actingAsAdmin();
        MembershipFeeTypeModel::factory()->create(['member_status' => MemberStatus::ACTIVE]);

        $response = $this->post(route('bo.invoicing.membership-fee-types.store'), [
            'name' => 'Autre cotisation active',
            'member_status' => MemberStatus::ACTIVE->value,
            'amount' => '120.00',
        ]);

        $response->assertSessionHasErrors('member_status');
        $this->assertDatabaseCount('membership_fee_types', 1);
    }

    public function test_rejects_a_duplicate_campaign_year(): void
    {
        $this->actingAsAdmin();
        MembershipFeeCampaign::factory()->create(['year' => 2027]);

        $response = $this->post(route('bo.invoicing.membership-fee-campaigns.store'), [
            'year' => 2027,
            'name' => 'Deuxième campagne',
            'issue_date' => '2027-01-01',
            'due_date' => '2027-02-01',
        ]);

        $response->assertSessionHasErrors('year');
        $this->assertDatabaseCount('membership_fee_campaigns', 1);
    }

    public function test_preview_creates_members_once_and_preserves_adjustments(): void
    {
        $this->actingAsAdmin();
        $this->configureFeeTypes();
        $campaign = $this->campaign();
        $active = $this->member('Alice', 'Martin');
        $passive = Member::factory()->passive()->create(['first_name' => 'Bob', 'last_name' => 'Durand']);

        $this->post(route('bo.invoicing.membership-fee-campaigns.preview', $campaign))
            ->assertRedirect();

        $this->assertDatabaseCount('membership_fee_campaign_members', 2);
        $this->assertDatabaseCount('billing_customers', 2);
        $this->assertSame(MembershipFeeCampaignStatus::Preview, $campaign->fresh()->status);

        $campaignMember = MembershipFeeCampaignMember::where('member_id', $active->id)->sole();
        $campaignMember->update([
            'discount_amount_cents' => 2500,
            'discount_reason' => 'Performance',
            'final_amount_cents' => 12500,
            'status' => CampaignMemberStatus::Excluded,
        ]);

        $this->post(route('bo.invoicing.membership-fee-campaigns.preview', $campaign))
            ->assertRedirect();

        $this->assertDatabaseCount('membership_fee_campaign_members', 2);
        $this->assertSame(2500, $campaignMember->fresh()->discount_amount_cents);
        $this->assertSame(CampaignMemberStatus::Excluded, $campaignMember->fresh()->status);
    }

    public function test_preview_fails_when_a_member_status_has_no_fee_type(): void
    {
        $this->actingAsAdmin();
        MembershipFeeTypeModel::factory()->create(['member_status' => MemberStatus::ACTIVE]);
        Member::factory()->passive()->create();
        $campaign = $this->campaign();

        $response = $this->post(route('bo.invoicing.membership-fee-campaigns.preview', $campaign));

        $response->assertSessionHasErrors('action');
        $this->assertDatabaseCount('membership_fee_campaign_members', 0);
    }

    public function test_requires_a_reason_for_full_exemption_and_updates_discount(): void
    {
        $this->actingAsAdmin();
        $this->configureFeeTypes();
        $campaign = $this->campaign();
        $this->member('Alice', 'Martin');
        $this->post(route('bo.invoicing.membership-fee-campaigns.preview', $campaign));

        $campaignMember = MembershipFeeCampaignMember::sole();

        $response = $this->put(route('bo.invoicing.membership-fee-campaigns.members.discount', [$campaign, $campaignMember]), [
            'discount' => '150.00',
            'reason' => '',
        ]);

        $response->assertSessionHasErrors('reason');
        $this->assertSame(0, $campaignMember->fresh()->discount_amount_cents);

        $this->put(route('bo.invoicing.membership-fee-campaigns.members.discount', [$campaign, $campaignMember]), [
            'discount' => '30.00',
            'reason' => 'Performance',
        ])->assertRedirect();

        $campaignMember->refresh();
        $this->assertSame(3000, $campaignMember->discount_amount_cents);
        $this->assertSame(12000, $campaignMember->final_amount_cents);
        $this->assertSame(CampaignMemberStatus::Pending, $campaignMember->status);
    }

    public function test_excludes_and_reintegrates_a_member(): void
    {
        $this->actingAsAdmin();
        $this->configureFeeTypes();
        $campaign = $this->campaign();
        $this->member('Alice', 'Martin');
        $this->post(route('bo.invoicing.membership-fee-campaigns.preview', $campaign));
        $campaignMember = MembershipFeeCampaignMember::sole();

        $this->post(route('bo.invoicing.membership-fee-campaigns.members.exclude', [$campaign, $campaignMember]))
            ->assertRedirect();

        $this->assertSame(CampaignMemberStatus::Excluded, $campaignMember->fresh()->status);

        $this->post(route('bo.invoicing.membership-fee-campaigns.members.include', [$campaign, $campaignMember]))
            ->assertRedirect();

        $this->assertSame(CampaignMemberStatus::Pending, $campaignMember->fresh()->status);
    }

    public function test_validates_campaign_generates_and_issues_invoices(): void
    {
        $this->actingAsAdmin();
        $this->configureFeeTypes();
        $campaign = $this->campaign();
        $this->member('Alice', 'Martin');
        $this->post(route('bo.invoicing.membership-fee-campaigns.preview', $campaign));

        $campaignMember = MembershipFeeCampaignMember::sole();
        $this->put(route('bo.invoicing.membership-fee-campaigns.members.discount', [$campaign, $campaignMember]), [
            'discount' => '30.00',
            'reason' => 'Performance',
        ]);

        $this->post(route('bo.invoicing.membership-fee-campaigns.validate', $campaign))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(MembershipFeeCampaignStatus::Validated, $campaign->fresh()->status);

        $this->post(route('bo.invoicing.membership-fee-campaigns.invoices.generate', $campaign))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invoice = Invoice::sole();
        $campaign->refresh();

        $this->assertSame(MembershipFeeCampaignStatus::Invoiced, $campaign->status);
        $this->assertSame($campaign->id, $invoice->membership_fee_campaign_id);
        $this->assertSame(12000, $invoice->total_cents);
        $this->assertCount(2, $invoice->items);
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);

        $this->post(route('bo.invoicing.membership-fee-campaigns.invoices.issue', $campaign))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertSame('2027-0001', $invoice->invoice_number);
        $this->assertSame(MembershipFeeCampaignStatus::Issued, $campaign->fresh()->status);
        $this->assertDatabaseHas('membership_fee_campaign_activity_logs', [
            'membership_fee_campaign_id' => $campaign->id,
            'action' => 'campaign.invoices_issued',
        ]);
    }

    public function test_full_exemption_invoice_becomes_paid_after_issue(): void
    {
        $this->actingAsAdmin();
        $this->configureFeeTypes();
        $campaign = $this->campaign();
        $this->member('Alice', 'Martin');
        $this->post(route('bo.invoicing.membership-fee-campaigns.preview', $campaign));
        $campaignMember = MembershipFeeCampaignMember::sole();

        $this->put(route('bo.invoicing.membership-fee-campaigns.members.discount', [$campaign, $campaignMember]), [
            'discount' => '150.00',
            'reason' => 'Décision du comité',
        ]);
        $this->post(route('bo.invoicing.membership-fee-campaigns.validate', $campaign));
        $this->post(route('bo.invoicing.membership-fee-campaigns.invoices.generate', $campaign));
        $this->post(route('bo.invoicing.membership-fee-campaigns.invoices.issue', $campaign));

        $invoice = Invoice::sole();

        $this->assertSame(0, $invoice->total_cents);
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame(2, $invoice->items()->count());
        $this->assertTrue($invoice->items()->where('type', 'discount')->exists());
    }

    public function test_batch_issue_reports_failures_and_keeps_the_campaign_invoiced(): void
    {
        $this->actingAsAdmin();
        $this->configureFeeTypes();
        $campaign = $this->campaign();
        $alice = $this->member('Alice', 'Martin');
        $this->member('Bob', 'Durand');
        $this->post(route('bo.invoicing.membership-fee-campaigns.preview', $campaign));
        $this->post(route('bo.invoicing.membership-fee-campaigns.validate', $campaign));
        $this->post(route('bo.invoicing.membership-fee-campaigns.invoices.generate', $campaign));

        BillingCustomer::query()->where('member_id', $alice->id)->update(['email' => null]);

        $response = $this->post(route('bo.invoicing.membership-fee-campaigns.invoices.issue', $campaign));

        $response->assertRedirect()->assertSessionHas('warning');
        $this->assertSame(1, Invoice::where('status', InvoiceStatus::Issued)->count());
        $this->assertSame(1, Invoice::where('status', InvoiceStatus::Draft)->count());
        $this->assertSame(MembershipFeeCampaignStatus::Invoiced, $campaign->fresh()->status);
    }

    public function test_deleting_a_campaign_draft_resets_the_campaign_to_validated(): void
    {
        $this->actingAsAdmin();
        $this->configureFeeTypes();
        $campaign = $this->campaign();
        $this->member('Alice', 'Martin');
        $this->post(route('bo.invoicing.membership-fee-campaigns.preview', $campaign));
        $this->post(route('bo.invoicing.membership-fee-campaigns.validate', $campaign));
        $this->post(route('bo.invoicing.membership-fee-campaigns.invoices.generate', $campaign));
        $invoice = Invoice::sole();

        $this->delete(route('bo.invoicing.invoices.destroy', $invoice))->assertRedirect();

        $this->assertSame(MembershipFeeCampaignStatus::Validated, $campaign->fresh()->status);
        $this->assertNull(MembershipFeeCampaignMember::sole()->invoice_id);
        $this->assertDatabaseHas('membership_fee_campaign_activity_logs', [
            'membership_fee_campaign_id' => $campaign->id,
            'action' => 'campaign.invoice_draft_deleted',
        ]);
    }

    public function test_viewer_can_read_but_cannot_manage_campaigns(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['access backoffice', 'view invoices']);
        $campaign = MembershipFeeCampaign::factory()->create();

        $this->actingAs($viewer)->get(route('bo.invoicing.membership-fee-campaigns.show', $campaign))->assertOk();
        $this->actingAs($viewer)->get(route('bo.invoicing.membership-fee-campaigns.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('bo.invoicing.membership-fee-campaigns.preview', $campaign))->assertForbidden();
    }

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        return $user;
    }

    private function configureFeeTypes(): void
    {
        MembershipFeeTypeModel::factory()->create([
            'name' => 'Cotisation membre actif',
            'member_status' => MemberStatus::ACTIVE,
            'amount_cents' => 15000,
        ]);

        MembershipFeeTypeModel::factory()->passive()->create([
            'amount_cents' => 5000,
        ]);
    }

    private function campaign(): MembershipFeeCampaign
    {
        return MembershipFeeCampaign::factory()->create([
            'year' => 2027,
            'name' => 'Cotisations 2027',
            'issue_date' => '2027-01-01',
            'due_date' => '2027-02-01',
            'status' => MembershipFeeCampaignStatus::Draft,
        ]);
    }

    private function member(string $firstName, string $lastName): Member
    {
        return Member::factory()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => strtolower($firstName).'@example.test',
        ]);
    }
}
