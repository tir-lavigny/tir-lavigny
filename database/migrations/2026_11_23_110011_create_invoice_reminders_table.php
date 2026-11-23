<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoice_reminders')) {
            return;
        }

        Schema::create('invoice_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('recipient');
            $table->bigInteger('outstanding_amount_cents');
            $table->string('status');
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users', 'id', 'invoice_reminders_processed_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_reminders');
    }
};
