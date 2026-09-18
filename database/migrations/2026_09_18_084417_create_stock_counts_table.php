<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_location_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->string('status');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('season_id');
            $table->index('stock_location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_counts');
    }
};
