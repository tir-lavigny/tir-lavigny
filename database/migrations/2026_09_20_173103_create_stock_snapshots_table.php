<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_location_id')->constrained()->restrictOnDelete();
            $table->timestamp('snapshot_at');
            $table->timestamps();

            $table->index('stock_location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_snapshots');
    }
};
