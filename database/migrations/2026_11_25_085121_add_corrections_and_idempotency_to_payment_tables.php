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

        if (! Schema::hasTable('invoice_payments')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('invoice_payments'));
        $hasUniquePair = $indexes->contains(
            fn (array $index): bool => $index['unique'] && $index['columns'] === ['invoice_id', 'payment_id'],
        );
        $hasPairIndex = $indexes->contains(
            fn (array $index): bool => ! $index['unique'] && $index['columns'] === ['invoice_id', 'payment_id'],
        );

        if ($hasUniquePair && ! $hasPairIndex) {
            Schema::table('invoice_payments', function (Blueprint $table): void {
                $table->index(['invoice_id', 'payment_id'], 'invoice_payments_invoice_id_payment_id_index');
            });
        }

        if ($hasUniquePair) {
            Schema::table('invoice_payments', function (Blueprint $table): void {
                $table->dropUnique('invoice_payments_invoice_id_payment_id_unique');
            });
        }

        if (! Schema::hasColumn('invoice_payments', 'idempotency_key')) {
            Schema::table('invoice_payments', function (Blueprint $table): void {
                $table->uuid('idempotency_key')->nullable()->unique();
            });
        }

        if (! Schema::hasColumn('invoice_payments', 'reversed_at')) {
            Schema::table('invoice_payments', function (Blueprint $table): void {
                $table->timestamp('reversed_at')->nullable();
            });
        }

        if (! Schema::hasColumn('invoice_payments', 'reversed_by')) {
            Schema::table('invoice_payments', function (Blueprint $table): void {
                $table->foreignId('reversed_by')
                    ->nullable()
                    ->constrained('users', 'id', 'invoice_payments_reversed_by_fk')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('invoice_payments', 'reversal_reason')) {
            Schema::table('invoice_payments', function (Blueprint $table): void {
                $table->text('reversal_reason')->nullable();
            });
        }

        $indexes = collect(Schema::getIndexes('invoice_payments'));
        $hasPairIndex = $indexes->contains(
            fn (array $index): bool => ! $index['unique'] && $index['columns'] === ['invoice_id', 'payment_id'],
        );

        if (! $hasPairIndex) {
            Schema::table('invoice_payments', function (Blueprint $table): void {
                $table->index(['invoice_id', 'payment_id'], 'invoice_payments_invoice_id_payment_id_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoice_payments') && Schema::hasColumn('invoice_payments', 'reversed_at')) {
            $indexes = collect(Schema::getIndexes('invoice_payments'));
            $hasUniquePair = $indexes->contains(
                fn (array $index): bool => $index['unique'] && $index['columns'] === ['invoice_id', 'payment_id'],
            );

            if (! $hasUniquePair) {
                Schema::table('invoice_payments', function (Blueprint $table): void {
                    $table->unique(['invoice_id', 'payment_id'], 'invoice_payments_invoice_id_payment_id_unique');
                });
            }

            if ($indexes->contains(
                fn (array $index): bool => ! $index['unique']
                    && $index['name'] === 'invoice_payments_invoice_id_payment_id_index',
            )) {
                Schema::table('invoice_payments', function (Blueprint $table): void {
                    $table->dropIndex('invoice_payments_invoice_id_payment_id_index');
                });
            }

            if (Schema::hasColumn('invoice_payments', 'reversed_by')) {
                Schema::table('invoice_payments', function (Blueprint $table): void {
                    $table->dropForeign('invoice_payments_reversed_by_fk');
                });

                Schema::table('invoice_payments', function (Blueprint $table): void {
                    $table->dropColumn('reversed_by');
                });
            }

            foreach (['idempotency_key', 'reversed_at', 'reversal_reason'] as $column) {
                if (! Schema::hasColumn('invoice_payments', $column)) {
                    continue;
                }

                Schema::table('invoice_payments', function (Blueprint $table) use ($column): void {
                    if ($column === 'idempotency_key') {
                        $table->dropUnique(['idempotency_key']);
                    }

                    $table->dropColumn($column);
                });
            }
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'idempotency_key')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropForeign('payments_voided_by_fk');
                $table->dropUnique(['idempotency_key']);
            });

            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn(['idempotency_key', 'voided_at', 'voided_by', 'void_reason']);
            });
        }
    }
};
