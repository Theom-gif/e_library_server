<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('avatar');
                $table->index('is_active');
            }

            if (!Schema::hasColumn('users', 'invitation_token')) {
                $table->string('invitation_token')->nullable()->unique()->after('is_active');
            }

            if (!Schema::hasColumn('users', 'invitation_sent_at')) {
                $table->timestamp('invitation_sent_at')->nullable()->after('invitation_token');
            }

            if (!Schema::hasColumn('users', 'invitation_accepted_at')) {
                $table->timestamp('invitation_accepted_at')->nullable()->after('invitation_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'invitation_accepted_at')) {
                $table->dropColumn('invitation_accepted_at');
            }

            if (Schema::hasColumn('users', 'invitation_sent_at')) {
                $table->dropColumn('invitation_sent_at');
            }

            if (Schema::hasColumn('users', 'invitation_token')) {
                $table->dropUnique('users_invitation_token_unique');
                $table->dropColumn('invitation_token');
            }

            if (Schema::hasColumn('users', 'is_active')) {
                $table->dropIndex(['is_active']);
                $table->dropColumn('is_active');
            }
        });
    }
};
