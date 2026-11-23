<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('membership_fee_campaigns')) {
            return;
        }

        Schema::create('membership_fee_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('name');
            $table->date('issue_date');
            $table->date('due_date');
            $table->string('status');
            $table->foreignId('created_by')->nullable()->constrained('users', 'id', 'mfc_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_fee_campaigns');
    }
};
