<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('financial_documents')) {
            return;
        }

        Schema::create('financial_documents', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable');
            $table->string('status');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->char('sha256', 64);
            $table->text('error')->nullable();
            $table->timestamp('generated_at');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['documentable_type', 'documentable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_documents');
    }
};
