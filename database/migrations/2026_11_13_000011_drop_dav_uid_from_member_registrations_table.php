<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('member_registrations', 'dav_uid')) {
            Schema::table('member_registrations', function (Blueprint $table) {
                $table->dropColumn('dav_uid');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('member_registrations', 'dav_uid')) {
            Schema::table('member_registrations', function (Blueprint $table) {
                $table->string('dav_uid')->nullable();
            });
        }
    }
};
