<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_account_entries')) {
            return;
        }

        Schema::create('customer_account_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->bigInteger('debit_cents')->default(0);
            $table->bigInteger('credit_cents')->default(0);
            $table->timestamp('occurred_at');
            $table->foreignId('created_by')->nullable()->constrained('users', 'id', 'customer_entries_created_by_fk')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['billing_customer_id', 'occurred_at']);
            $table->index(['invoice_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_account_entries');
    }
};
