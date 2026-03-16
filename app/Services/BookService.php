<?php

namespace App\Services;

use App\Models\Book;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BookService
{
    public function listApproved(int $perPage = 15): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, 100));

        return Book::query()
            ->where('status', 'approved')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findApproved(int $bookId): ?Book
    {
        return Book::query()
            ->where('id', $bookId)
            ->where('status', 'approved')
            ->first();
    }
}
