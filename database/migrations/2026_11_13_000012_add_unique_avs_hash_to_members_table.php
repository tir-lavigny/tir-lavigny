<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('members')
            ->select('avs_hash')
            ->whereNotNull('avs_hash')
            ->groupBy('avs_hash')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('avs_hash');

        if ($duplicates->isNotEmpty()) {
            $memberIds = DB::table('members')
                ->whereIn('avs_hash', $duplicates)
                ->pluck('id')
                ->implode(', ');

            throw new RuntimeException(
                'Duplicate AVS hashes found for member ids: '.$memberIds.'. Resolve them before adding the unique index.'
            );
        }

        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasIndex('members', 'members_avs_hash_unique')) {
                $table->unique('avs_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (Schema::hasIndex('members', 'members_avs_hash_unique')) {
                $table->dropUnique(['avs_hash']);
            }
        });
    }
};
