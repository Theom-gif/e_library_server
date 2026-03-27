<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('last_page')->default(1);
            $table->decimal('progress_percent', 5, 2)->default(0.00);
            $table->unsignedBigInteger('total_seconds')->default(0);
            $table->unsignedInteger('total_sessions')->default(0);
            $table->unsignedInteger('total_days')->default(0);

            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['book_id', 'user_id']);
            $table->index(['user_id', 'last_read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_progress');
    }
};
