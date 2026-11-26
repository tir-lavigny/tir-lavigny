<?php

namespace Tests\Feature\BackOffice\Invoicing;

use App\Domains\Invoicing\Models\MembershipFeeCampaign;
use App\Domains\Invoicing\Models\MembershipFeeCampaignMember;
use App\Domains\Invoicing\Models\Payment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\IssuesInvoices;
use Tests\TestCase;

class InvoicingExportTest extends TestCase
{
    use IssuesInvoices;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_invoice_export_route_is_registered_before_the_show_route(): void
    {
        $this->assertTrue(Route::has('bo.invoicing.invoices.export'));
        $this->assertSame('/back-office/invoicing/invoices/export', route('bo.invoicing.invoices.export', absolute: false));
    }

    public function test_payment_export_route_is_registered_before_the_show_route(): void
    {
        $this->assertTrue(Route::has('bo.invoicing.payments.export'));
        $this->assertSame('/back-office/invoicing/payments/export', route('bo.invoicing.payments.export', absolute: false));
    }

    public function test_invoice_csv_export_contains_balance_columns_and_rows(): void
    {
        $invoice = $this->issueInvoice();

        $response = $this->get(route('bo.invoicing.invoices.export', ['format' => 'csv']));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('Numéro', $content);
        $this->assertStringContainsString('Reste CHF', $content);
        $this->assertStringContainsString($invoice->invoice_number, $content);
        $this->assertStringContainsString('Alice Martin', $content);
    }

    public function test_payment_csv_export_contains_payment_rows(): void
    {
        $this->invoicingAdmin();

        Payment::factory()->create([
            'amount_cents' => 10000,
            'payment_date' => '2027-02-20',
            'reference' => 'PAY-EXPORT',
        ]);

        $response = $this->get(route('bo.invoicing.payments.export', ['format' => 'csv']));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('Référence', $content);
        $this->assertStringContainsString('PAY-EXPORT', $content);
        $this->assertStringContainsString('100.00', $content);
    }

    public function test_csv_export_neutralizes_formula_cells(): void
    {
        $this->invoicingAdmin();

        Payment::factory()->create([
            'amount_cents' => 10000,
            'payment_date' => '2027-02-20',
            'reference' => '=HYPERLINK("https://example.test")',
        ]);

        $response = $this->get(route('bo.invoicing.payments.export', ['format' => 'csv']));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringNotContainsString("\n=HYPERLINK", $content);
    }

    public function test_campaign_csv_export_contains_member_rows(): void
    {
        $this->invoicingAdmin();
        $campaign = MembershipFeeCampaign::factory()->create(['year' => 2027]);
        MembershipFeeCampaignMember::factory()->for($campaign, 'campaign')->create([
            'fee_type_name' => 'Cotisation active',
            'base_amount_cents' => 15000,
            'discount_amount_cents' => 3000,
            'discount_reason' => 'Performance sportive',
            'final_amount_cents' => 12000,
        ]);

        $response = $this->get(route('bo.invoicing.membership-fee-campaigns.export', [$campaign, 'format' => 'csv']));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('Membre', $content);
        $this->assertStringContainsString('Cotisation active', $content);
        $this->assertStringContainsString('Performance sportive', $content);
        $this->assertStringContainsString('120.00', $content);
    }

    public function test_invoice_xlsx_export_returns_an_excel_file(): void
    {
        $this->issueInvoice();

        $response = $this->get(route('bo.invoicing.invoices.export', ['format' => 'xlsx']));

        $response->assertOk();
        $this->assertStringStartsWith('PK', $response->streamedContent());
    }

    public function test_invalid_export_format_is_rejected(): void
    {
        $this->invoicingAdmin();

        $this->from(route('bo.invoicing.invoices.index'))
            ->get(route('bo.invoicing.invoices.export', ['format' => 'pdf']))
            ->assertRedirect(route('bo.invoicing.invoices.index'))
            ->assertSessionHasErrors('format');
    }

    public function test_user_without_permission_cannot_export_invoices(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access backoffice');

        $this->actingAs($user)
            ->get(route('bo.invoicing.invoices.export'))
            ->assertForbidden();
    }

    public function test_payment_export_requires_payment_view_permission(): void
    {
        $invoiceViewer = User::factory()->create();
        $invoiceViewer->givePermissionTo(['access backoffice', 'view invoices']);

        $paymentViewer = User::factory()->create();
        $paymentViewer->givePermissionTo(['access backoffice', 'view payments']);

        $this->actingAs($invoiceViewer)
            ->get(route('bo.invoicing.payments.export'))
            ->assertForbidden();

        $this->actingAs($paymentViewer)
            ->get(route('bo.invoicing.payments.export'))
            ->assertOk();
    }

    public function test_campaign_export_requires_membership_fee_management(): void
    {
        $this->invoicingAdmin();
        $campaign = MembershipFeeCampaign::factory()->create(['year' => 2027]);
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['access backoffice', 'view invoices']);

        $this->actingAs($viewer)
            ->get(route('bo.invoicing.membership-fee-campaigns.export', $campaign))
            ->assertForbidden();
    }
}
