<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('membership_fee_types', 'is_active')) {
            Schema::table('membership_fee_types', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('membership_fee_types', 'is_active')) {
            Schema::table('membership_fee_types', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('amount_cents');
            });
        }
    }
};
