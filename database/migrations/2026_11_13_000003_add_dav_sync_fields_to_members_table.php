<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'dav_synced_at')) {
                $table->timestamp('dav_synced_at')->nullable();
            }
        });

        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'dav_synced_snapshot')) {
                $table->json('dav_synced_snapshot')->nullable();
            }
        });

        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'dav_sync_error')) {
                $table->text('dav_sync_error')->nullable();
            }
        });
    }

    public function down(): void
    {
        foreach (['dav_synced_at', 'dav_synced_snapshot', 'dav_sync_error'] as $column) {
            Schema::table('members', function (Blueprint $table) use ($column) {
                if (Schema::hasColumn('members', $column)) {
                    $table->dropColumn($column);
                }
            });
        }
    }
};
