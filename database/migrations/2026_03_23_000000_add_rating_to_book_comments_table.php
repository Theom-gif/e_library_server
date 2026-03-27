<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_comments', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating')->nullable()->after('content');
            $table->index(['book_id', 'rating']);
        });
    }

    public function down(): void
    {
        Schema::table('book_comments', function (Blueprint $table) {
            $table->dropIndex(['book_id', 'rating']);
            $table->dropColumn('rating');
        });
    }
};
