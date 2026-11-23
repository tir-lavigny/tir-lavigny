<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integer cents storage for cash_movements: the new column becomes the source of
 * truth, the decimal column is kept (and mirrored) until phase 2 drops it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cash_movements', 'amount_cents')) {
            Schema::table('cash_movements', function (Blueprint $table) {
                $table->bigInteger('amount_cents')->nullable()->after('amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cash_movements', 'amount_cents')) {
            Schema::table('cash_movements', function (Blueprint $table) {
                $table->dropColumn('amount_cents');
            });
        }
    }
};
