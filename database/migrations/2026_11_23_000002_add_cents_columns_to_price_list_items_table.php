<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integer cents storage for price_list_items: the new column becomes the source of
 * truth, the decimal column is kept (and mirrored) until phase 2 drops it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('price_list_items', 'unit_price_cents')) {
            Schema::table('price_list_items', function (Blueprint $table) {
                $table->integer('unit_price_cents')->nullable()->after('unit_price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('price_list_items', 'unit_price_cents')) {
            Schema::table('price_list_items', function (Blueprint $table) {
                $table->dropColumn('unit_price_cents');
            });
        }
    }
};
