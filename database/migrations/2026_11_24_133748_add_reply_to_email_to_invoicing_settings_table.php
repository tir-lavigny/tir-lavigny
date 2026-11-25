<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('invoicing_settings') || Schema::hasColumn('invoicing_settings', 'reply_to_email')) {
            return;
        }

        Schema::table('invoicing_settings', function (Blueprint $table) {
            $table->string('reply_to_email')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('invoicing_settings', 'reply_to_email')) {
            return;
        }

        Schema::table('invoicing_settings', function (Blueprint $table) {
            $table->dropColumn('reply_to_email');
        });
    }
};
