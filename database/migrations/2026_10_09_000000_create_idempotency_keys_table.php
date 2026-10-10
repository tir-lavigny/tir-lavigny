<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('idempotency_keys')) {
            Schema::create('idempotency_keys', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->string('key');
                $table->string('request_hash');
                $table->string('status')->default('processing');
                $table->unsignedSmallInteger('response_status')->nullable();
                $table->longText('response_body')->nullable();
                $table->timestamp('locked_until')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'key']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('idempotency_keys')) {
            Schema::dropIfExists('idempotency_keys');
        }
    }
};
