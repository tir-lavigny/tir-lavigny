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
        Schema::create('member_registrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('form_uuid')->unique();
            $table->string('status');
            $table->string('dav_uid')->nullable();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->json('form_data')->nullable();
            $table->timestamp('form_submitted_at')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_registrations');
    }
};
