<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('invoice_deliveries') || Schema::hasColumn('invoice_deliveries', 'batch_id')) {
            return;
        }

        Schema::table('invoice_deliveries', function (Blueprint $table) {
            $table->uuid('batch_id')->nullable();
            $table->text('error')->nullable();
            $table->index(['channel', 'status']);
            $table->index('batch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('invoice_deliveries') || ! Schema::hasColumn('invoice_deliveries', 'batch_id')) {
            return;
        }

        Schema::table('invoice_deliveries', function (Blueprint $table) {
            $table->dropIndex(['channel', 'status']);
            $table->dropIndex(['batch_id']);
            $table->dropColumn(['batch_id', 'error']);
        });
    }
};
