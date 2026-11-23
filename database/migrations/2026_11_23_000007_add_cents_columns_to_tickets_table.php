<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integer cents storage for tickets: the new column becomes the source of
 * truth, the decimal column is kept (and mirrored) until phase 2 drops it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tickets', 'total_cents')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->bigInteger('total_cents')->nullable()->after('total');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tickets', 'total_cents')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropColumn('total_cents');
            });
        }
    }
};
