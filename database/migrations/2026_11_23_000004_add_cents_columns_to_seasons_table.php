<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integer cents storage for seasons: the new column becomes the source of
 * truth, the decimal column is kept (and mirrored) until phase 2 drops it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('seasons', 'initial_balance_cents')) {
            Schema::table('seasons', function (Blueprint $table) {
                $table->integer('initial_balance_cents')->nullable()->after('initial_balance');
            });
        }

        if (! Schema::hasColumn('seasons', 'end_balance_cents')) {
            Schema::table('seasons', function (Blueprint $table) {
                $table->integer('end_balance_cents')->nullable()->after('end_balance');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('seasons', 'initial_balance_cents')) {
            Schema::table('seasons', function (Blueprint $table) {
                $table->dropColumn('initial_balance_cents');
            });
        }

        if (Schema::hasColumn('seasons', 'end_balance_cents')) {
            Schema::table('seasons', function (Blueprint $table) {
                $table->dropColumn('end_balance_cents');
            });
        }
    }
};
