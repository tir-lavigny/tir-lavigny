<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoicing_settings')) {
            return;
        }

        Schema::create('invoicing_settings', function (Blueprint $table) {
            $table->id();
            $table->string('creditor_name');
            $table->string('creditor_address');
            $table->string('creditor_postal_code', 20);
            $table->string('creditor_city');
            $table->char('creditor_country', 2)->default('CH');
            $table->string('creditor_iban', 34);
            $table->foreignId('updated_by')->nullable()->constrained('users', 'id', 'invoicing_settings_updated_by_fk')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_settings');
    }
};
