<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('credit_notes')) {
            return;
        }

        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('billing_customer_id')->constrained()->restrictOnDelete();
            $table->string('credit_note_number', 20)->nullable()->unique();
            $table->string('status');
            $table->text('reason');
            $table->date('issue_date')->nullable();
            $table->char('currency', 3)->default('CHF');
            $table->bigInteger('subtotal_cents');
            $table->bigInteger('total_cents');
            $table->string('recipient_name');
            $table->string('recipient_address');
            $table->string('recipient_postal_code', 20);
            $table->string('recipient_city');
            $table->char('recipient_country', 2);
            $table->string('recipient_email')->nullable();
            $table->string('creditor_name')->nullable();
            $table->string('creditor_address')->nullable();
            $table->string('creditor_postal_code', 20)->nullable();
            $table->string('creditor_city')->nullable();
            $table->char('creditor_country', 2)->nullable();
            $table->string('creditor_iban', 34)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', 'id', 'credit_notes_created_by_fk')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
            $table->index('billing_customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
