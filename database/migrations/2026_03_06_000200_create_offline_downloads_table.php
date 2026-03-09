<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('local_identifier')->nullable();
            $table->timestamp('downloaded_at')->useCurrent();
            $table->timestamp('last_synced_at')->nullable();
            $table->enum('sync_status', ['pending', 'synced', 'conflict'])->default('pending');
            $table->timestamps();

            $table->unique(['book_id', 'user_id']);
            $table->index(['user_id', 'sync_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_downloads');
    }
};
