<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('credit_note_items')) {
            return;
        }

        Schema::create('credit_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained('invoice_items', 'id', 'cni_invoice_item_fk')->restrictOnDelete();
            $table->text('description');
            $table->decimal('quantity', 10, 3)->default(1);
            $table->bigInteger('unit_price_cents');
            $table->bigInteger('total_cents');
            $table->unsignedInteger('position')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['credit_note_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_items');
    }
};
