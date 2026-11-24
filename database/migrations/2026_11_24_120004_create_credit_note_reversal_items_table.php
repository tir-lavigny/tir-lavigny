<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('credit_note_reversal_items')) {
            return;
        }

        Schema::create('credit_note_reversal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_reversal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_note_item_id')->nullable()->constrained('credit_note_items', 'id', 'cnr_items_credit_note_item_fk')->restrictOnDelete();
            $table->text('description');
            $table->bigInteger('amount_cents');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['credit_note_reversal_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_reversal_items');
    }
};
