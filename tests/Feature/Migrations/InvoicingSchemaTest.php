<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InvoicingSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, string>
     */
    public static function tables(): array
    {
        return [
            'billing_customers',
            'invoices',
            'invoice_items',
            'invoice_sequences',
            'payments',
            'invoice_payments',
            'membership_fee_types',
            'membership_fee_campaigns',
            'membership_fee_campaign_members',
            'invoicing_settings',
            'financial_documents',
            'invoice_deliveries',
            'invoice_reminders',
            'invoice_activity_logs',
            'customer_account_entries',
            'credit_note_sequences',
            'credit_notes',
            'credit_note_items',
            'credit_note_applications',
            'credit_note_application_reversals',
            'credit_note_refunds',
            'credit_note_reversal_sequences',
            'credit_note_reversals',
            'credit_note_reversal_items',
        ];
    }

    public function test_all_invoicing_tables_are_created(): void
    {
        foreach (self::tables() as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table [{$table}] is missing.");
        }
    }

    public function test_invoice_amounts_are_stored_in_integer_cents(): void
    {
        $this->assertTrue(Schema::hasColumns('invoices', ['subtotal_cents', 'total_cents']));
        $this->assertTrue(Schema::hasColumns('invoice_items', ['unit_price_cents', 'total_cents']));
        $this->assertTrue(Schema::hasColumns('payments', ['amount_cents']));
        $this->assertTrue(Schema::hasColumns('membership_fee_campaign_members', [
            'base_amount_cents',
            'discount_amount_cents',
            'final_amount_cents',
        ]));
        $this->assertTrue(Schema::hasColumns('invoicing_settings', ['reply_to_email', 'envelope_window']));
        $this->assertTrue(Schema::hasColumns('invoice_deliveries', ['batch_id', 'error']));
    }

    public function test_invoice_number_and_sequence_year_are_unique(): void
    {
        $this->assertUniqueIndex('invoices', ['invoice_number']);
        $this->assertUniqueIndex('invoice_sequences', ['year']);
        $this->assertUniqueIndex('credit_notes', ['credit_note_number']);
        $this->assertUniqueIndex('credit_note_sequences', ['year']);
        $this->assertUniqueIndex('credit_note_reversals', ['credit_note_reversal_number']);
        $this->assertUniqueIndex('credit_note_reversal_sequences', ['year']);
    }

    public function test_member_has_one_billing_customer(): void
    {
        $this->assertUniqueIndex('billing_customers', ['member_id']);
    }

    public function test_campaign_member_and_payment_allocation_are_unique(): void
    {
        $this->assertUniqueIndex('membership_fee_campaign_members', ['membership_fee_campaign_id', 'member_id']);
        $this->assertUniqueIndex('invoice_payments', ['invoice_id', 'payment_id']);
        $this->assertUniqueIndex('financial_documents', ['documentable_type', 'documentable_id']);
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function assertUniqueIndex(string $table, array $columns): void
    {
        $indexes = collect(Schema::getIndexes($table));

        $this->assertTrue(
            $indexes->contains(fn (array $index): bool => $index['unique'] && $index['columns'] === $columns),
            "No unique index on [{$table}] for [".implode(', ', $columns).'].',
        );
    }
}
