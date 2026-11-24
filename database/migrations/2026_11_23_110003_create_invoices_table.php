<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices')) {
            return;
        }

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('membership_fee_campaign_id')
                ->nullable()
                ->constrained('membership_fee_campaigns', 'id', 'invoices_campaign_fk')
                ->nullOnDelete();
            $table->string('type');
            $table->string('status');
            $table->string('invoice_number', 16)->nullable()->unique();
            $table->string('recipient_name');
            $table->string('recipient_address');
            $table->string('recipient_postal_code', 20);
            $table->string('recipient_city');
            $table->char('recipient_country', 2);
            $table->string('recipient_email')->nullable();
            $table->string('delivery_method');
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->char('currency', 3)->default('CHF');
            $table->bigInteger('subtotal_cents');
            $table->bigInteger('total_cents');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users', 'id', 'invoices_cancelled_by_fk')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->string('payment_reference', 32)->nullable();
            $table->string('creditor_name')->nullable();
            $table->string('creditor_address')->nullable();
            $table->string('creditor_postal_code', 20)->nullable();
            $table->string('creditor_city')->nullable();
            $table->char('creditor_country', 2)->nullable();
            $table->string('creditor_iban', 34)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', 'id', 'invoices_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('type');
            $table->index('issue_date');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
