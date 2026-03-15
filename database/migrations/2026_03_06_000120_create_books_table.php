<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id(); // id
            $table->string('title'); // title
            $table->string('author'); // author
            $table->text('description')->nullable(); // description
            $table->string('category'); // category
            $table->year('published_year')->nullable(); // published_year
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // user_id
            $table->string('cover_image_path')->nullable(); // cover image path
            $table->string('book_file_path')->nullable(); // book file path
            $table->string('cover_image_url')->nullable(); // cover image url
            $table->string('book_file_url')->nullable(); // book file url

            // Optional / extra fields
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->bigInteger('total_reads')->default(0);
            $table->decimal('average_rating', 3, 2)->default(0);

            $table->timestamps(); // created_at, updated_at
            $table->softDeletes(); // deleted_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
