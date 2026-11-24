<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_account_entries') || Schema::hasColumn('customer_account_entries', 'credit_note_id')) {
            return;
        }

        Schema::table('customer_account_entries', function (Blueprint $table) {
            $table->foreignId('credit_note_id')
                ->nullable()
                ->after('invoice_id')
                ->constrained('credit_notes', 'id', 'customer_entries_credit_note_fk')
                ->nullOnDelete();

            $table->index(['credit_note_id', 'type']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('customer_account_entries', 'credit_note_id')) {
            return;
        }

        Schema::table('customer_account_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_note_id');
        });
    }
};
