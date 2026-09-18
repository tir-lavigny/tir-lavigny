<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }
        if (! Schema::hasColumn('products', 'is_stockable')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('is_stockable')->default(true);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('products', 'is_stockable')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('is_stockable');
            });
        }
    }
};
