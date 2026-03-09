<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('original_pdf_name')->nullable()->after('pdf_path');
            $table->string('pdf_mime_type', 120)->nullable()->after('original_pdf_name');
            $table->string('original_cover_name')->nullable()->after('cover_image_path');
            $table->string('cover_mime_type', 120)->nullable()->after('original_cover_name');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn([
                'original_pdf_name',
                'pdf_mime_type',
                'original_cover_name',
                'cover_mime_type',
            ]);
        });
    }
};
