<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_registrations', function (Blueprint $table) {
            if (! Schema::hasIndex('member_registrations', 'member_registrations_member_id_unique')) {
                $table->unique('member_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('member_registrations', function (Blueprint $table) {
            if (Schema::hasIndex('member_registrations', 'member_registrations_member_id_unique')) {
                $table->dropUnique(['member_id']);
            }
        });
    }
};
