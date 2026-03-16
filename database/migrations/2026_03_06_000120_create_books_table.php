<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
           // $table->foreignId('author_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->string('slug')->unique();

            $table->text('description')->nullable();
            $table->string('author_name')->nullable();

            $table->string('pdf_path')->nullable();
            $table->string('original_pdf_name')->nullable();
            $table->string('pdf_mime_type')->nullable();

            $table->string('cover_image_path')->nullable();
            $table->string('original_cover_name')->nullable();
            $table->string('cover_mime_type')->nullable();

            $table->bigInteger('file_size_bytes')->nullable();
            $table->integer('total_pages')->nullable();

            $table->string('language')->nullable();

            $table->enum('status', ['pending','approved','rejected'])->default('pending');

            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->bigInteger('total_reads')->default(0);
            $table->decimal('average_rating',3,2)->default(0);

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
