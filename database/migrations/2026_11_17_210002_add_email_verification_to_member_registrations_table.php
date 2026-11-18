<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('member_registrations', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable();
            }
        });

        Schema::table('member_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('member_registrations', 'email_verification_code_hash')) {
                $table->string('email_verification_code_hash')->nullable();
            }
        });

        Schema::table('member_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('member_registrations', 'email_verification_expires_at')) {
                $table->timestamp('email_verification_expires_at')->nullable();
            }
        });

        Schema::table('member_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('member_registrations', 'email_verification_attempts')) {
                $table->unsignedTinyInteger('email_verification_attempts')->default(0);
            }
        });

        Schema::table('member_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('member_registrations', 'email_verification_sent_at')) {
                $table->timestamp('email_verification_sent_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('member_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('member_registrations', 'email_verified_at')) {
                $table->dropColumn('email_verified_at');
            }
        });

        Schema::table('member_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('member_registrations', 'email_verification_code_hash')) {
                $table->dropColumn('email_verification_code_hash');
            }
        });

        Schema::table('member_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('member_registrations', 'email_verification_expires_at')) {
                $table->dropColumn('email_verification_expires_at');
            }
        });

        Schema::table('member_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('member_registrations', 'email_verification_attempts')) {
                $table->dropColumn('email_verification_attempts');
            }
        });

        Schema::table('member_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('member_registrations', 'email_verification_sent_at')) {
                $table->dropColumn('email_verification_sent_at');
            }
        });
    }
};
