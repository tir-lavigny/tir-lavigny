<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'email')) {
                $table->string('email')->nullable();
            }
        });

        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'tel')) {
                $table->string('tel')->nullable();
            }
        });

        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'avs')) {
                $table->text('avs')->nullable();
            }
        });

        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'avs_hash')) {
                $table->string('avs_hash', 64)->nullable();
            }
        });

        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'is_swiss_citizen')) {
                $table->boolean('is_swiss_citizen')->nullable();
            }
        });

        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'street')) {
                $table->string('street')->nullable();
            }
        });

        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'npa')) {
                $table->string('npa', 10)->nullable();
            }
        });

        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'city')) {
                $table->string('city')->nullable();
            }
        });

        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'canton')) {
                $table->string('canton', 2)->nullable();
            }
        });

        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'country')) {
                $table->string('country')->nullable();
            }
        });

        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasIndex('members', 'members_email_index')) {
                $table->index('email');
            }
        });

        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasIndex('members', 'members_avs_hash_index')) {
                $table->index('avs_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (Schema::hasIndex('members', 'members_email_index')) {
                $table->dropIndex(['email']);
            }
        });

        Schema::table('members', function (Blueprint $table) {
            if (Schema::hasIndex('members', 'members_avs_hash_index')) {
                $table->dropIndex(['avs_hash']);
            }
        });

        foreach (['email', 'tel', 'avs', 'avs_hash', 'is_swiss_citizen', 'street', 'npa', 'city', 'canton', 'country'] as $column) {
            Schema::table('members', function (Blueprint $table) use ($column) {
                if (Schema::hasColumn('members', $column)) {
                    $table->dropColumn($column);
                }
            });
        }
    }
};
