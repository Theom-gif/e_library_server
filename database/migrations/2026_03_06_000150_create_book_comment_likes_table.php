<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_comment_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_comment_id')->constrained('book_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['book_comment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_comment_likes');
    }
};
