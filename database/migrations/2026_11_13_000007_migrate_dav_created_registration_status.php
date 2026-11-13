<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('member_registrations')
            ->where('status', 'dav_created')
            ->update(['status' => 'member_created']);
    }

    public function down(): void
    {
        DB::table('member_registrations')
            ->where('status', 'member_created')
            ->update(['status' => 'dav_created']);
    }
};
