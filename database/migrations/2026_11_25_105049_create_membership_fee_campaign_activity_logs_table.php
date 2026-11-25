<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('membership_fee_campaign_activity_logs')) {
            return;
        }

        Schema::create('membership_fee_campaign_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_fee_campaign_id')
                ->constrained('membership_fee_campaigns', 'id', 'mfc_activity_campaign_fk')
                ->cascadeOnDelete();
            $table->foreignId('membership_fee_campaign_member_id')
                ->nullable()
                ->constrained('membership_fee_campaign_members', 'id', 'mfc_activity_member_fk')
                ->nullOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users', 'id', 'mfc_activity_user_fk')
                ->nullOnDelete();
            $table->string('action');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['membership_fee_campaign_id', 'created_at'], 'mfc_activity_campaign_created_index');
            $table->index(['membership_fee_campaign_member_id', 'created_at'], 'mfc_activity_member_created_index');
            $table->index('action', 'mfc_activity_action_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_fee_campaign_activity_logs');
    }
};
