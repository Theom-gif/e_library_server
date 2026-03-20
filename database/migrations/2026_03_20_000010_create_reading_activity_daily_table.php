<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_activity_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('activity_date');
            $table->unsignedInteger('seconds_read')->default(0);
            $table->unsignedInteger('minutes_read')->default(0);
            $table->unsignedInteger('books_opened_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'activity_date']);
            $table->index(['user_id', 'activity_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_activity_daily');
    }
};
