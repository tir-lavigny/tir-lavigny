<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoicing_settings') || Schema::hasColumn('invoicing_settings', 'envelope_window')) {
            return;
        }

        Schema::table('invoicing_settings', function (Blueprint $table) {
            $table->string('envelope_window', 10)->default('left');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('invoicing_settings', 'envelope_window')) {
            return;
        }

        Schema::table('invoicing_settings', function (Blueprint $table) {
            $table->dropColumn('envelope_window');
        });
    }
};
