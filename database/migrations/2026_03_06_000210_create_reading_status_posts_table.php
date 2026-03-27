<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_status_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('status_type', ['started', 'progress', 'finished', 'quote', 'custom'])->default('custom');
            $table->text('content');
            $table->unsignedInteger('current_page')->nullable();
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('likes_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['book_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_status_posts');
    }
};
