<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoice_reminders') || Schema::hasColumn('invoice_reminders', 'error')) {
            return;
        }

        Schema::table('invoice_reminders', function (Blueprint $table) {
            $table->text('error')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoice_reminders') || ! Schema::hasColumn('invoice_reminders', 'error')) {
            return;
        }

        Schema::table('invoice_reminders', function (Blueprint $table) {
            $table->dropColumn('error');
        });
    }
};
