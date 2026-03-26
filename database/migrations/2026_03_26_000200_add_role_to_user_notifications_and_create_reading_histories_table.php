<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('user_notifications', 'role')) {
                $table->enum('role', ['user', 'author', 'admin'])->default('user')->after('created_by_user_id');
                $table->index(['role', 'is_read', 'created_at']);
            }
        });

        if (!Schema::hasTable('reading_histories')) {
            Schema::create('reading_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('book_id')->constrained()->cascadeOnDelete();
                $table->timestamp('started_at');
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'book_id']);
                $table->index(['user_id', 'started_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('reading_histories')) {
            Schema::dropIfExists('reading_histories');
        }

        Schema::table('user_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('user_notifications', 'role')) {
                $table->dropIndex(['role', 'is_read', 'created_at']);
                $table->dropColumn('role');
            }
        });
    }
};
