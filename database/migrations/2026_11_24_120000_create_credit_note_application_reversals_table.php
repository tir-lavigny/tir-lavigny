<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('credit_note_application_reversals')) {
            return;
        }

        Schema::create('credit_note_application_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_application_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_cents');
            $table->text('reason');
            $table->timestamp('reversed_at');
            $table->foreignId('created_by')->nullable()->constrained('users', 'id', 'cna_reversals_created_by_fk')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('credit_note_application_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_application_reversals');
    }
};
