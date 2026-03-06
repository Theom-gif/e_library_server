<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('start_page')->nullable();
            $table->unsignedInteger('end_page')->nullable();

            $table->string('device_type', 30)->nullable();
            $table->boolean('is_offline')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'started_at']);
            $table->index(['book_id', 'started_at']);
            $table->index(['is_offline', 'synced_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_sessions');
    }
};
