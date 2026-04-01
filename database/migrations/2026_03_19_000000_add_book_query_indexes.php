<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfPossible('books', ['status', 'published_at'], 'books_status_published_at_index');
        $this->addIndexIfPossible('books', ['status', 'category_id'], 'books_status_category_id_index');
        $this->addIndexIfPossible('books', ['author_id', 'status'], 'books_author_id_status_index');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('books', 'books_status_published_at_index');
        $this->dropIndexIfExists('books', 'books_status_category_id_index');
        $this->dropIndexIfExists('books', 'books_author_id_status_index');
    }

    private function indexExists(string $table, string $index): bool
    {
        $driver = DB::getDriverName();
        $database = DB::getDatabaseName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('$table')");

            foreach ($indexes as $existingIndex) {
                if (($existingIndex->name ?? null) === $index) {
                    return true;
                }
            }

            return false;
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    /**
     * @param array<int, string> $columns
     */
    private function addIndexIfPossible(string $table, array $columns, string $indexName): void
    {
        if (!Schema::hasTable($table) || $this->indexExists($table, $indexName)) {
            return;
        }

        foreach ($columns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return;
            }
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
                $blueprint->index($columns, $indexName);
            });
        } catch (QueryException $exception) {
            if ((int) $exception->getCode() !== 42000 || !str_contains($exception->getMessage(), '1061')) {
                throw $exception;
            }
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!Schema::hasTable($table) || !$this->indexExists($table, $indexName)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
                $blueprint->dropIndex($indexName);
            });
        } catch (QueryException $exception) {
            if ((int) $exception->getCode() !== 42000) {
                throw $exception;
            }
        }
    }
};
