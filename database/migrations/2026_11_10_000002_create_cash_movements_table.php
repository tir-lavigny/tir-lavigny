<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cash_movements')) {
            Schema::create('cash_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('season_id')->constrained()->cascadeOnDelete();
                $table->decimal('amount', 10, 2);
                $table->date('occurred_at');
                $table->string('remark')->nullable();
                $table->timestamps();

                $table->index(['season_id', 'occurred_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cash_movements')) {
            Schema::drop('cash_movements');
        }
    }
};
