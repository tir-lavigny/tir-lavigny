<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('billing_customers')) {
            return;
        }

        if (! Schema::hasColumn('billing_customers', 'sync_with_member')) {
            Schema::table('billing_customers', function (Blueprint $table): void {
                $table->boolean('sync_with_member')->default(true);
            });
        }

        if (! Schema::hasColumn('billing_customers', 'last_synced_at')) {
            Schema::table('billing_customers', function (Blueprint $table): void {
                $table->timestamp('last_synced_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('billing_customers')) {
            return;
        }

        if (Schema::hasColumn('billing_customers', 'last_synced_at')) {
            Schema::table('billing_customers', function (Blueprint $table): void {
                $table->dropColumn('last_synced_at');
            });
        }

        if (Schema::hasColumn('billing_customers', 'sync_with_member')) {
            Schema::table('billing_customers', function (Blueprint $table): void {
                $table->dropColumn('sync_with_member');
            });
        }
    }
};
