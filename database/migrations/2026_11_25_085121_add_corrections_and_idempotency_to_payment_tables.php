<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments') && ! Schema::hasColumn('payments', 'idempotency_key')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->uuid('idempotency_key')->nullable()->unique();
                $table->timestamp('voided_at')->nullable();
                $table->foreignId('voided_by')->nullable()->constrained('users', 'id', 'payments_voided_by_fk')->nullOnDelete();
                $table->text('void_reason')->nullable();
            });
        }

        if (! Schema::hasTable('invoice_payments') || Schema::hasColumn('invoice_payments', 'reversed_at')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('invoice_payments'));
        $hasUniquePair = $indexes->contains(
            fn (array $index): bool => $index['unique'] && $index['columns'] === ['invoice_id', 'payment_id'],
        );

        Schema::table('invoice_payments', function (Blueprint $table) use ($hasUniquePair): void {
            if ($hasUniquePair) {
                $table->dropUnique(['invoice_id', 'payment_id']);
            }

            $table->uuid('idempotency_key')->nullable()->unique();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users', 'id', 'invoice_payments_reversed_by_fk')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->index(['invoice_id', 'payment_id']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('invoice_payments') && Schema::hasColumn('invoice_payments', 'reversed_at')) {
            Schema::table('invoice_payments', function (Blueprint $table) {
                $table->dropIndex(['invoice_id', 'payment_id']);
                $table->dropForeign(['reversed_by']);
                $table->dropColumn(['idempotency_key', 'reversed_at', 'reversed_by', 'reversal_reason']);
                $table->unique(['invoice_id', 'payment_id']);
            });
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'idempotency_key')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropForeign(['voided_by']);
                $table->dropColumn(['idempotency_key', 'voided_at', 'voided_by', 'void_reason']);
            });
        }
    }
};
