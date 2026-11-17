<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('members')
            ->select('dav_uid')
            ->whereNotNull('dav_uid')
            ->groupBy('dav_uid')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('dav_uid')
            ->each(function (string $davUid): void {
                $duplicateIds = DB::table('members')
                    ->where('dav_uid', $davUid)
                    ->orderBy('id')
                    ->pluck('id')
                    ->slice(1);

                DB::table('members')
                    ->whereIn('id', $duplicateIds)
                    ->update(['dav_uid' => null, 'dav_synced_snapshot' => null]);
            });

        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasIndex('members', 'members_dav_uid_unique')) {
                $table->unique('dav_uid');
            }
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (Schema::hasIndex('members', 'members_dav_uid_unique')) {
                $table->dropUnique(['dav_uid']);
            }
        });
    }
};
