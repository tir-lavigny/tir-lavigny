<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::rename('activity_logs', 'activity_logs_old');
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id();
                $table->string('model');
                $table->string('model_id');
                $table->string('action');
                $table->json('changes')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
            DB::statement('INSERT INTO activity_logs (id, model, model_id, action, changes, user_id, created_at, updated_at) SELECT id, model, model_id, action, changes, user_id, created_at, updated_at FROM activity_logs_old');
            Schema::drop('activity_logs_old');
        } else {
            DB::statement('ALTER TABLE activity_logs MODIFY model_id VARCHAR(255) NOT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::rename('activity_logs', 'activity_logs_old');
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id();
                $table->string('model');
                $table->unsignedBigInteger('model_id');
                $table->string('action');
                $table->json('changes')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
            DB::statement('INSERT INTO activity_logs (id, model, model_id, action, changes, user_id, created_at, updated_at) SELECT id, model, model_id, action, changes, user_id, created_at, updated_at FROM activity_logs_old');
            Schema::drop('activity_logs_old');
        } else {
            DB::statement('ALTER TABLE activity_logs MODIFY model_id BIGINT UNSIGNED NOT NULL');
        }
    }
};
