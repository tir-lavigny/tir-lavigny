<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_chains', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('recurrence_type')->comment('Allowed values enforced at app layer: daily, weekly, biweekly, monthly, custom (or any future type)');
            $table->unsignedSmallInteger('interval')->nullable()->default(1)->comment('Used for custom recurrence: repeat every N interval_units');
            $table->string('interval_unit')->nullable()->comment('Unit for custom interval, e.g. days or weeks');
            $table->tinyInteger('day_of_week')->nullable()->comment('0=Monday … 6=Sunday; used for weekly/biweekly');
            $table->tinyInteger('day_of_month')->nullable()->comment('1-31; used for monthly');
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('content_id')->nullable()->constrained()->nullOnDelete();
            $table->text('custom_body')->nullable()->comment('Chain-level custom content body (takes precedence over content_id)');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_chains');
    }
};
