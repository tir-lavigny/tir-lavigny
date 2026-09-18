<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->foreignId('season_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index('from_location_id');
            $table->index('to_location_id');
            $table->index('season_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};