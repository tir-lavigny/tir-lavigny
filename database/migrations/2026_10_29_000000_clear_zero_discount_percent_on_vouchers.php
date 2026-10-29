<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Clear a zero percent discount on vouchers that carry a fixed amount.
     *
     * Earlier form versions stored 0 instead of null for the inactive
     * discount type, which made fixed vouchers behave as 0% vouchers.
     */
    public function up(): void
    {
        DB::table('vouchers')
            ->where('discount_percent', 0)
            ->whereNotNull('discount_fixed')
            ->where('discount_fixed', '>', 0)
            ->update(['discount_percent' => null]);
    }

    public function down(): void {}
};
