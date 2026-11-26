<?php

namespace Tests\Feature\BackOffice\Invoicing;

use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\InvoicingSettings;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvoicingSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_settings_page_renders_for_manager(): void
    {
        $this->actingAs($this->admin())
            ->get(route('bo.invoicing.settings.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('backOffice/Invoicing/Settings/Edit')
                ->where('configured', true)
            );
    }

    public function test_view_only_user_cannot_access_settings(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['access backoffice', 'view invoices']);

        $this->actingAs($user)
            ->get(route('bo.invoicing.settings.edit'))
            ->assertForbidden();
    }

    public function test_rejects_invalid_iban(): void
    {
        $this->actingAs($this->admin())
            ->put(route('bo.invoicing.settings.update'), $this->settingsData([
                'creditor_iban' => 'CH00 0000 0000 0000 0000 0',
            ]))
            ->assertSessionHasErrors('creditor_iban');
    }

    public function test_rejects_a_qr_iban_for_scor_references(): void
    {
        $this->actingAs($this->admin())
            ->put(route('bo.invoicing.settings.update'), $this->settingsData([
                'creditor_iban' => 'CH4431999123000889012',
            ]))
            ->assertSessionHasErrors('creditor_iban');
    }

    public function test_normalizes_iban_before_saving(): void
    {
        $this->actingAs($this->admin())
            ->put(route('bo.invoicing.settings.update'), $this->settingsData([
                'creditor_iban' => 'ch93 0076 2011 6238 5295 7',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('invoicing_settings', [
            'creditor_iban' => 'CH9300762011623852957',
            'creditor_country' => 'CH',
        ]);
    }

    public function test_saves_envelope_window_side(): void
    {
        $this->actingAs($this->admin())
            ->put(route('bo.invoicing.settings.update'), $this->settingsData([
                'envelope_window' => 'right',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('invoicing_settings', [
            'envelope_window' => 'right',
        ]);
    }

    public function test_rejects_invalid_envelope_window_side(): void
    {
        $this->actingAs($this->admin())
            ->put(route('bo.invoicing.settings.update'), $this->settingsData([
                'envelope_window' => 'top',
            ]))
            ->assertSessionHasErrors('envelope_window');
    }

    public function test_saves_reply_to_email(): void
    {
        $this->actingAs($this->admin())
            ->put(route('bo.invoicing.settings.update'), $this->settingsData([
                'reply_to_email' => 'tresorier@example.test',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('invoicing_settings', [
            'reply_to_email' => 'tresorier@example.test',
        ]);
    }

    public function test_rejects_invalid_reply_to_email(): void
    {
        $this->actingAs($this->admin())
            ->put(route('bo.invoicing.settings.update'), $this->settingsData([
                'reply_to_email' => 'not-an-email',
            ]))
            ->assertSessionHasErrors('reply_to_email');
    }

    public function test_missing_settings_block_invoice_creation(): void
    {
        InvoicingSettings::query()->delete();
        $customer = BillingCustomer::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('bo.invoicing.invoices.create'))
            ->assertRedirect(route('bo.invoicing.settings.edit'))
            ->assertSessionHasErrors('settings');

        $this->actingAs($this->admin())
            ->post(route('bo.invoicing.invoices.store'), $this->invoiceData($customer->id))
            ->assertRedirect(route('bo.invoicing.settings.edit'))
            ->assertSessionHasErrors('settings');

        $this->assertDatabaseCount('invoices', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function settingsData(array $overrides = []): array
    {
        return [
            'creditor_name' => 'Société de Tir à Lavigny',
            'creditor_address' => 'Rue du Stand 1',
            'creditor_postal_code' => '1175',
            'creditor_city' => 'Lavigny',
            'creditor_country' => 'CH',
            'creditor_iban' => 'CH9300762011623852957',
            'reply_to_email' => null,
            'envelope_window' => 'left',
            ...$overrides,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceData(int $customerId): array
    {
        return [
            'billing_customer_id' => $customerId,
            'delivery_method' => 'email',
            'issue_date' => '2027-01-15',
            'due_date' => '2027-02-14',
            'items' => [
                ['type' => 'fee', 'description' => 'Cotisation', 'quantity' => '1', 'unit_price' => '150.00'],
            ],
        ];
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }
}
