<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('credit_note_applications')) {
            return;
        }

        Schema::create('credit_note_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_cents');
            $table->timestamp('applied_at');
            $table->foreignId('created_by')->nullable()->constrained('users', 'id', 'credit_applications_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['credit_note_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_applications');
    }
};
