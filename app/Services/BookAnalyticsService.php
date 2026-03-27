<?php

namespace App\Services;

use App\Models\Book;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BookAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function forBook(Book $book): array
    {
        $metrics = $this->forBooks([$book]);

        return $metrics[$book->id] ?? $this->emptyMetrics();
    }

    /**
     * @param  iterable<Book>  $books
     * @return array<int, array<string, mixed>>
     */
    public function forBooks(iterable $books): array
    {
        $bookIds = collect($books)
            ->map(fn (Book $book) => (int) $book->id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($bookIds)) {
            return [];
        }

        $totalReaders = DB::table('reading_sessions')
            ->select('book_id', DB::raw('COUNT(DISTINCT user_id) as total_readers'))
            ->whereIn('book_id', $bookIds)
            ->groupBy('book_id')
            ->pluck('total_readers', 'book_id');

        $completedReaders = DB::table('reading_progress')
            ->select('book_id', DB::raw('COUNT(DISTINCT user_id) as completed_readers'))
            ->whereIn('book_id', $bookIds)
            ->whereNotNull('completed_at')
            ->groupBy('book_id')
            ->pluck('completed_readers', 'book_id');

        $monthlyReads = DB::table('reading_sessions')
            ->select('book_id', DB::raw('COUNT(*) as monthly_reads'))
            ->whereIn('book_id', $bookIds)
            ->where('started_at', '>=', CarbonImmutable::now()->startOfMonth())
            ->groupBy('book_id')
            ->pluck('monthly_reads', 'book_id');

        $totalReads = DB::table('reading_sessions')
            ->select('book_id', DB::raw('COUNT(*) as total_reads'))
            ->whereIn('book_id', $bookIds)
            ->groupBy('book_id')
            ->pluck('total_reads', 'book_id');

        $metrics = [];

        foreach ($bookIds as $bookId) {
            $totalReadersCount = (int) ($totalReaders[$bookId] ?? 0);
            $completedReadersCount = (int) ($completedReaders[$bookId] ?? 0);

            $metrics[$bookId] = [
                'id' => $bookId,
                'totalReaders' => $totalReadersCount,
                'completionRate' => $totalReadersCount > 0
                    ? round(($completedReadersCount / $totalReadersCount) * 100, 2)
                    : 0.0,
                'monthlyReads' => (int) ($monthlyReads[$bookId] ?? 0),
                'totalReads' => (int) ($totalReads[$bookId] ?? 0),
                'completedReaders' => $completedReadersCount,
            ];
        }

        return $metrics;
    }

    /**
     * @return array<string, mixed>
     */
    public function emptyMetrics(): array
    {
        return [
            'id' => null,
            'totalReaders' => 0,
            'completionRate' => 0.0,
            'monthlyReads' => 0,
            'totalReads' => 0,
            'completedReaders' => 0,
        ];
    }
}
