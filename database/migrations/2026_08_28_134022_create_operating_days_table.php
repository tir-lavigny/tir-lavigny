<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('operating_days', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->dateTime('started_at');
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->text('cash_remark')->nullable();
            $table->decimal('closing_balance', 10, 2)->nullable();
            $table->decimal('total_cash', 10, 2)->default(0);
            $table->decimal('total_twint', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['season_id', 'date']);
            $table->index('season_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operating_days');
    }
};
