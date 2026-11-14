<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('member_registrations')
            ->select('member_id')
            ->whereNotNull('member_id')
            ->groupBy('member_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('member_id')
            ->each(function (int $memberId): void {
                $duplicateIds = DB::table('member_registrations')
                    ->where('member_id', $memberId)
                    ->orderBy('id')
                    ->pluck('id')
                    ->slice(1);

                DB::table('member_registrations')
                    ->whereIn('id', $duplicateIds)
                    ->update(['member_id' => null]);
            });

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
