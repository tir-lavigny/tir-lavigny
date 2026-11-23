<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integer cents storage for vouchers: the new column becomes the source of
 * truth, the decimal column is kept (and mirrored) until phase 2 drops it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('vouchers', 'discount_fixed_cents')) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->bigInteger('discount_fixed_cents')->nullable()->after('discount_fixed');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vouchers', 'discount_fixed_cents')) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->dropColumn('discount_fixed_cents');
            });
        }
    }
};
