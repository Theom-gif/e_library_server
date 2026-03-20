<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reading_sessions', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('duration_seconds');
            $table->string('source', 30)->nullable()->after('device_type');
            $table->timestamp('last_heartbeat_at')->nullable()->after('ended_at');
            $table->timestamp('last_activity_at')->nullable()->after('last_heartbeat_at');
            $table->decimal('last_progress_percent', 5, 2)->nullable()->after('end_page');
            $table->unsignedInteger('heartbeat_count')->default(0)->after('last_progress_percent');

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'last_activity_at']);
        });
    }

    public function down(): void
    {
        Schema::table('reading_sessions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['user_id', 'last_activity_at']);
            $table->dropColumn([
                'status',
                'source',
                'last_heartbeat_at',
                'last_activity_at',
                'last_progress_percent',
                'heartbeat_count',
            ]);
        });
    }
};
