<?php

namespace Tests\Feature\BackOffice\Invoicing;

use App\Domains\Invoicing\Actions\Invoices\CreateInvoice;
use App\Domains\Invoicing\Actions\Invoices\IssueInvoice;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\Invoice;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CreditNoteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_create_page_is_rendered_from_an_issued_invoice(): void
    {
        $this->actingAsAdmin();
        $invoice = $this->issuedInvoice(10000);

        $this->get(route('bo.invoicing.credit-notes.create', ['invoice_id' => $invoice->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('backOffice/Invoicing/CreditNotes/Create')
                ->where('invoice.id', $invoice->id)
                ->where('remainingCreditableCents', 10000)
            );
    }

    public function test_creates_a_credit_note_draft(): void
    {
        $this->actingAsAdmin();
        $invoice = $this->issuedInvoice(10000);

        $response = $this->post(route('bo.invoicing.credit-notes.store'), [
            'invoice_id' => $invoice->id,
            'reason' => 'Correction',
            'issue_date' => '2027-01-20',
            'items' => [
                ['description' => 'Avoir', 'quantity' => '1', 'unit_price' => '40.00'],
            ],
        ]);

        $creditNote = CreditNote::firstOrFail();

        $response->assertRedirect(route('bo.invoicing.credit-notes.show', $creditNote));
        $this->assertSame('draft', $creditNote->status->value);
        $this->assertSame(4000, $creditNote->total_cents);
        $this->assertCount(1, $creditNote->items);
    }

    public function test_rejects_a_credit_note_above_the_remaining_creditable_amount(): void
    {
        $this->actingAsAdmin();
        $invoice = $this->issuedInvoice(10000);

        $response = $this->post(route('bo.invoicing.credit-notes.store'), [
            'invoice_id' => $invoice->id,
            'reason' => 'Trop',
            'items' => [
                ['description' => 'Avoir', 'quantity' => '1', 'unit_price' => '101.00'],
            ],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseCount('credit_notes', 0);
    }

    public function test_issues_a_credit_note_and_applies_it(): void
    {
        $this->actingAsAdmin();
        $invoice = $this->issuedInvoice(10000);
        $this->post(route('bo.invoicing.credit-notes.store'), [
            'invoice_id' => $invoice->id,
            'reason' => 'Correction',
            'issue_date' => '2027-01-20',
            'items' => [
                ['description' => 'Avoir', 'quantity' => '1', 'unit_price' => '40.00'],
            ],
        ]);
        $creditNote = CreditNote::firstOrFail();

        $response = $this->post(route('bo.invoicing.credit-notes.issue', $creditNote));

        $response->assertRedirect(route('bo.invoicing.credit-notes.show', $creditNote));
        $creditNote->refresh();
        $this->assertSame('fully_used', $creditNote->status->value);
        $this->assertSame('CN-2027-0001', $creditNote->credit_note_number);
        $this->assertSame(4000, (int) $creditNote->applications()->sum('amount_cents'));
    }

    public function test_updates_and_deletes_a_credit_note_draft(): void
    {
        $this->actingAsAdmin();
        $invoice = $this->issuedInvoice(10000);
        $this->post(route('bo.invoicing.credit-notes.store'), [
            'invoice_id' => $invoice->id,
            'reason' => 'Correction',
            'items' => [
                ['description' => 'Avoir', 'quantity' => '1', 'unit_price' => '40.00'],
            ],
        ]);
        $creditNote = CreditNote::firstOrFail();

        $this->put(route('bo.invoicing.credit-notes.update', $creditNote), [
            'reason' => 'Correction mise à jour',
            'items' => [
                ['description' => 'Avoir', 'quantity' => '1', 'unit_price' => '30.00'],
            ],
        ])->assertRedirect(route('bo.invoicing.credit-notes.show', $creditNote));

        $this->assertSame(3000, $creditNote->fresh()->total_cents);

        $this->delete(route('bo.invoicing.credit-notes.destroy', $creditNote))
            ->assertRedirect(route('bo.invoicing.credit-notes.index'));

        $this->assertModelMissing($creditNote);
    }

    public function test_view_only_user_cannot_create_credit_notes(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['access backoffice', 'view invoices']);
        $invoice = $this->issuedInvoice(10000);

        $this->actingAs($viewer)
            ->get(route('bo.invoicing.credit-notes.create', ['invoice_id' => $invoice->id]))
            ->assertForbidden();
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        return $admin;
    }

    private function issuedInvoice(int $amountCents): Invoice
    {
        $actor = User::factory()->create();
        $customer = BillingCustomer::factory()->create();
        $invoice = app(CreateInvoice::class)->execute([
            'billing_customer_id' => $customer->id,
            'delivery_method' => 'email',
            'issue_date' => '2027-01-15',
            'due_date' => '2027-02-14',
            'items' => [
                ['type' => 'fee', 'description' => 'Prestation', 'quantity' => '1', 'unit_price' => number_format($amountCents / 100, 2, '.', '')],
            ],
        ], $actor);

        return app(IssueInvoice::class)->execute($invoice, $actor);
    }
}
