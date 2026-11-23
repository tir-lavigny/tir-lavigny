<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integer cents storage for operating_days: the new column becomes the source of
 * truth, the decimal column is kept (and mirrored) until phase 2 drops it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('operating_days', 'closing_balance_cents')) {
            Schema::table('operating_days', function (Blueprint $table) {
                $table->bigInteger('closing_balance_cents')->nullable()->after('closing_balance');
            });
        }

        if (! Schema::hasColumn('operating_days', 'total_cash_cents')) {
            Schema::table('operating_days', function (Blueprint $table) {
                $table->bigInteger('total_cash_cents')->nullable()->after('total_cash');
            });
        }

        if (! Schema::hasColumn('operating_days', 'total_twint_cents')) {
            Schema::table('operating_days', function (Blueprint $table) {
                $table->bigInteger('total_twint_cents')->nullable()->after('total_twint');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('operating_days', 'closing_balance_cents')) {
            Schema::table('operating_days', function (Blueprint $table) {
                $table->dropColumn('closing_balance_cents');
            });
        }

        if (Schema::hasColumn('operating_days', 'total_cash_cents')) {
            Schema::table('operating_days', function (Blueprint $table) {
                $table->dropColumn('total_cash_cents');
            });
        }

        if (Schema::hasColumn('operating_days', 'total_twint_cents')) {
            Schema::table('operating_days', function (Blueprint $table) {
                $table->dropColumn('total_twint_cents');
            });
        }
    }
};
