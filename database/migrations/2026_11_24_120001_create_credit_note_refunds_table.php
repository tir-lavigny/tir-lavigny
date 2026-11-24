<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('credit_note_refunds')) {
            return;
        }

        Schema::create('credit_note_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_cents');
            $table->date('refund_date');
            $table->string('payment_method');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', 'id', 'credit_note_refunds_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['credit_note_id', 'refund_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_refunds');
    }
};
