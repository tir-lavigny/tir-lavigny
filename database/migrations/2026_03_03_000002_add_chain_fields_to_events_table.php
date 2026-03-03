<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('event_chain_id')
                ->nullable()
                ->after('location_id')
                ->constrained('event_chains')
                ->nullOnDelete();

            $table->json('overridden_fields')
                ->nullable()
                ->after('event_chain_id')
                ->comment('List of field names individually customised on this event, bypassing chain propagation');

            $table->boolean('has_chain_conflict')
                ->default(false)
                ->after('overridden_fields')
                ->comment('True when a chain update tried to modify an overridden field');

            $table->json('pending_conflict_fields')
                ->nullable()
                ->after('has_chain_conflict')
                ->comment('Fields flagged as conflicting during the last chain update, pending admin resolution');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['event_chain_id']);
            $table->dropColumn(['event_chain_id', 'overridden_fields', 'has_chain_conflict', 'pending_conflict_fields']);
        });
    }
};
