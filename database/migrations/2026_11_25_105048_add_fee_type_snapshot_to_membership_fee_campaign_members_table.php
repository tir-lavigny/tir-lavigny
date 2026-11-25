<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('membership_fee_campaign_members', 'membership_fee_type_id')) {
            Schema::table('membership_fee_campaign_members', function (Blueprint $table) {
                $table->foreignId('membership_fee_type_id')
                    ->nullable()
                    ->after('billing_customer_id')
                    ->constrained('membership_fee_types', 'id', 'mfc_members_fee_type_fk')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('membership_fee_campaign_members', 'fee_type_name')) {
            Schema::table('membership_fee_campaign_members', function (Blueprint $table) {
                $table->string('fee_type_name')->nullable()->after('membership_fee_type_id');
            });
        }
    }

    public function down(): void
    {
        if (collect(Schema::getForeignKeys('membership_fee_campaign_members'))->contains(
            fn (array $foreignKey): bool => $foreignKey['columns'] === ['membership_fee_type_id'],
        )) {
            Schema::table('membership_fee_campaign_members', function (Blueprint $table) {
                $table->dropForeign('mfc_members_fee_type_fk');
            });
        }

        if (Schema::hasColumn('membership_fee_campaign_members', 'fee_type_name')) {
            Schema::table('membership_fee_campaign_members', function (Blueprint $table) {
                $table->dropColumn('fee_type_name');
            });
        }

        if (Schema::hasColumn('membership_fee_campaign_members', 'membership_fee_type_id')) {
            Schema::table('membership_fee_campaign_members', function (Blueprint $table) {
                $table->dropColumn('membership_fee_type_id');
            });
        }
    }
};
