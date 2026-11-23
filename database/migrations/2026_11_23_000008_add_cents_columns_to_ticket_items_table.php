<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integer cents storage for ticket_items: the new column becomes the source of
 * truth, the decimal column is kept (and mirrored) until phase 2 drops it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ticket_items', 'unit_price_cents')) {
            Schema::table('ticket_items', function (Blueprint $table) {
                $table->integer('unit_price_cents')->nullable()->after('unit_price');
            });
        }

        if (! Schema::hasColumn('ticket_items', 'subtotal_cents')) {
            Schema::table('ticket_items', function (Blueprint $table) {
                $table->integer('subtotal_cents')->nullable()->after('subtotal');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ticket_items', 'unit_price_cents')) {
            Schema::table('ticket_items', function (Blueprint $table) {
                $table->dropColumn('unit_price_cents');
            });
        }

        if (Schema::hasColumn('ticket_items', 'subtotal_cents')) {
            Schema::table('ticket_items', function (Blueprint $table) {
                $table->dropColumn('subtotal_cents');
            });
        }
    }
};
