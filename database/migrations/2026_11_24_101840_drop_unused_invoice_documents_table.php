<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoice_documents')) {
            return;
        }

        if (DB::table('invoice_documents')->exists()) {
            return;
        }

        Schema::drop('invoice_documents');
    }

    public function down(): void
    {
        if (Schema::hasTable('invoice_documents')) {
            return;
        }

        Schema::create('invoice_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->char('sha256', 64);
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->index('invoice_id');
        });
    }
};
