<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('billing_customers')) {
            return;
        }

        Schema::create('billing_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('organization_name')->nullable();
            $table->string('address');
            $table->string('postal_code', 20);
            $table->string('city');
            $table->char('country', 2)->default('CH');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('preferred_invoice_delivery')->default('postal');
            $table->timestamps();

            $table->index(['last_name', 'first_name']);
            $table->index('organization_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_customers');
    }
};
