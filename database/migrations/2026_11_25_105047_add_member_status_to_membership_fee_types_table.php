<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('membership_fee_types', 'member_status')) {
            Schema::table('membership_fee_types', function (Blueprint $table) {
                $table->string('member_status')->nullable()->after('name');
            });
        }

        if (! Schema::hasIndex('membership_fee_types', 'membership_fee_types_member_status_unique')) {
            Schema::table('membership_fee_types', function (Blueprint $table) {
                $table->unique('member_status', 'membership_fee_types_member_status_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('membership_fee_types', 'membership_fee_types_member_status_unique')) {
            Schema::table('membership_fee_types', function (Blueprint $table) {
                $table->dropUnique('membership_fee_types_member_status_unique');
            });
        }

        if (Schema::hasColumn('membership_fee_types', 'member_status')) {
            Schema::table('membership_fee_types', function (Blueprint $table) {
                $table->dropColumn('member_status');
            });
        }
    }
};
