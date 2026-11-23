<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('membership_fee_campaign_members')) {
            return;
        }

        Schema::create('membership_fee_campaign_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_fee_campaign_id')
                ->constrained('membership_fee_campaigns', 'id', 'mfc_members_campaign_fk')
                ->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('billing_customer_id')->constrained()->restrictOnDelete();
            $table->bigInteger('base_amount_cents');
            $table->bigInteger('discount_amount_cents')->default(0);
            $table->text('discount_reason')->nullable();
            $table->bigInteger('final_amount_cents');
            $table->string('status');
            $table->string('delivery_method');
            $table->foreignId('validated_by')->nullable()->constrained('users', 'id', 'mfc_members_validated_by_fk')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices', 'id', 'mfc_members_invoice_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['membership_fee_campaign_id', 'member_id'], 'mfc_members_campaign_member_unique');
            $table->index('member_id');
            $table->index('billing_customer_id');
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_fee_campaign_members');
    }
};
