<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            if (!$this->indexExists('books', 'books_status_published_at_index')) {
                $table->index(['status', 'published_at'], 'books_status_published_at_index');
            }

            if (!$this->indexExists('books', 'books_status_category_id_index')) {
                $table->index(['status', 'category_id'], 'books_status_category_id_index');
            }

            if (!$this->indexExists('books', 'books_author_id_status_index')) {
                $table->index(['author_id', 'status'], 'books_author_id_status_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            if ($this->indexExists('books', 'books_status_published_at_index')) {
                $table->dropIndex('books_status_published_at_index');
            }

            if ($this->indexExists('books', 'books_status_category_id_index')) {
                $table->dropIndex('books_status_category_id_index');
            }

            if ($this->indexExists('books', 'books_author_id_status_index')) {
                $table->dropIndex('books_author_id_status_index');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
