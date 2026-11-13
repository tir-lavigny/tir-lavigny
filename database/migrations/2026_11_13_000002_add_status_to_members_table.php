<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'status')) {
                $table->string('status')->default('active');
            }
        });

        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasIndex('members', 'members_status_index')) {
                $table->index('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (Schema::hasIndex('members', 'members_status_index')) {
                $table->dropIndex(['status']);
            }
        });

        Schema::table('members', function (Blueprint $table) {
            if (Schema::hasColumn('members', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
