<?php

namespace App\Services;

use App\Support\PublicImage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuthorDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function getDashboard(int $authorId, string $range = '6m', int $topBooksLimit = 5, int $feedbackLimit = 5): array
    {
        return [
            'stats' => $this->getStats($authorId),
            'performance' => $this->getPerformance($authorId, $range),
            'topBooks' => $this->getTopBooks($authorId, $topBooksLimit)['data'],
            'feedback' => $this->getFeedback($authorId, $feedbackLimit)['data'],
            'demographics' => $this->getDemographics($authorId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(int $authorId): array
    {
        $allBooksQuery = $this->authorBooksBaseQuery($authorId);

        $totalBooks = (clone $allBooksQuery)->count();
        $approvedBooks = (clone $allBooksQuery)->where('status', 'approved')->count();
        $pendingBooks = (clone $allBooksQuery)->where('status', 'pending')->count();
        $rejectedBooks = (clone $allBooksQuery)->where('status', 'rejected')->count();

        $bookIds = $this->authorBookIdsSubquery($authorId);

        $totalReads = (int) DB::table('reading_sessions')
            ->whereIn('book_id', $bookIds)
            ->count();

        $totalReaders = (int) DB::table('reading_sessions')
            ->whereIn('book_id', $this->authorBookIdsSubquery($authorId))
            ->distinct('user_id')
            ->count('user_id');

        $averageRating = (float) DB::table('book_ratings')
            ->whereIn('book_id', $this->authorBookIdsSubquery($authorId))
            ->avg('rating');

        $feedbackCount = (int) DB::table('book_comments')
            ->whereIn('book_id', $this->authorBookIdsSubquery($authorId))
            ->count();

        $activeReaders = (int) DB::table('reading_sessions')
            ->whereIn('book_id', $this->authorBookIdsSubquery($authorId))
            ->where('started_at', '>=', CarbonImmutable::now()->subDays(30))
            ->distinct('user_id')
            ->count('user_id');

        $currentWindowStart = CarbonImmutable::now()->subDays(30);
        $previousWindowStart = CarbonImmutable::now()->subDays(60);
        $previousWindowEnd = $currentWindowStart;

        $booksThisWindow = (clone $this->authorBooksBaseQuery($authorId))
            ->where('created_at', '>=', $currentWindowStart)
            ->count();
        $booksPreviousWindow = (clone $this->authorBooksBaseQuery($authorId))
            ->where('created_at', '>=', $previousWindowStart)
            ->where('created_at', '<', $previousWindowEnd)
            ->count();

        $readsThisWindow = (int) DB::table('reading_sessions')
            ->whereIn('book_id', $this->authorBookIdsSubquery($authorId))
            ->where('started_at', '>=', $currentWindowStart)
            ->count();
        $readsPreviousWindow = (int) DB::table('reading_sessions')
            ->whereIn('book_id', $this->authorBookIdsSubquery($authorId))
            ->where('started_at', '>=', $previousWindowStart)
            ->where('started_at', '<', $previousWindowEnd)
            ->count();

        $readersThisWindow = (int) DB::table('reading_sessions')
            ->whereIn('book_id', $this->authorBookIdsSubquery($authorId))
            ->where('started_at', '>=', $currentWindowStart)
            ->distinct('user_id')
            ->count('user_id');
        $readersPreviousWindow = (int) DB::table('reading_sessions')
            ->whereIn('book_id', $this->authorBookIdsSubquery($authorId))
            ->where('started_at', '>=', $previousWindowStart)
            ->where('started_at', '<', $previousWindowEnd)
            ->distinct('user_id')
            ->count('user_id');

        return [
            'stats' => [
                'totalSales' => 0,
                'totalReaders' => $totalReaders,
                'activeReaders' => $activeReaders,
                'totalReads' => $totalReads,
                'averageRating' => round($averageRating, 2),
                'totalBooks' => $totalBooks,
                'approvedBooks' => $approvedBooks,
                'pendingBooks' => $pendingBooks,
                'rejectedBooks' => $rejectedBooks,
                'totalFeedback' => $feedbackCount,
            ],
            'trends' => [
                'totalSales' => 0,
                'totalReaders' => $readersThisWindow - $readersPreviousWindow,
                'activeReaders' => $readersThisWindow - $readersPreviousWindow,
                'totalReads' => $readsThisWindow - $readsPreviousWindow,
                'totalBooks' => $booksThisWindow - $booksPreviousWindow,
                'averageRating' => 0,
            ],
            'meta' => [
                'sales_available' => false,
                'active_readers_window_days' => 30,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getPerformance(int $authorId, string $range = '6m'): array
    {
        $range = in_array($range, ['30d', '6m', '12m'], true) ? $range : '6m';

        $now = CarbonImmutable::now();
        [$start, $isMonthly] = match ($range) {
            '30d' => [$now->subDays(29)->startOfDay(), false],
            '12m' => [$now->startOfMonth()->subMonths(11), true],
            default => [$now->startOfMonth()->subMonths(5), true],
        };

        $bookIds = DB::table('books')
            ->select('id')
            ->whereIn('id', $this->authorBookIdsSubquery($authorId))
            ->pluck('id');

        $sessions = DB::table('reading_sessions')
            ->select('book_id', 'user_id', 'started_at', 'duration_seconds')
            ->whereIn('book_id', $bookIds)
            ->where('started_at', '>=', $start)
            ->get();

        $books = DB::table('books')
            ->select('id', 'created_at', 'published_at')
            ->whereIn('id', $bookIds)
            ->get();

        $points = [];

        if ($isMonthly) {
            $cursor = $start;
            $end = $now->startOfMonth();
            while ($cursor->lte($end)) {
                $key = $cursor->format('Y-m');
                $bucketSessions = $sessions->filter(fn (object $row) => CarbonImmutable::parse($row->started_at)->format('Y-m') === $key);
                $bucketBooks = $books->filter(function (object $row) use ($key) {
                    $date = $row->published_at ?? $row->created_at;
                    return $date && CarbonImmutable::parse($date)->format('Y-m') === $key;
                });

                $points[] = [
                    'key' => $key,
                    'label' => $cursor->format('M Y'),
                    'sales' => 0,
                    'reads' => $bucketSessions->count(),
                    'activeReaders' => $bucketSessions->pluck('user_id')->unique()->count(),
                    'uniqueReaders' => $bucketSessions->pluck('user_id')->unique()->count(),
                    'minutesRead' => (int) round($bucketSessions->sum('duration_seconds') / 60),
                    'booksPublished' => $bucketBooks->count(),
                ];

                $cursor = $cursor->addMonth();
            }
        } else {
            $cursor = $start;
            $end = $now->startOfDay();
            while ($cursor->lte($end)) {
                $key = $cursor->toDateString();
                $bucketSessions = $sessions->filter(fn (object $row) => CarbonImmutable::parse($row->started_at)->toDateString() === $key);
                $bucketBooks = $books->filter(function (object $row) use ($key) {
                    $date = $row->published_at ?? $row->created_at;
                    return $date && CarbonImmutable::parse($date)->toDateString() === $key;
                });

                $points[] = [
                    'key' => $key,
                    'label' => $cursor->format('M j'),
                    'sales' => 0,
                    'reads' => $bucketSessions->count(),
                    'activeReaders' => $bucketSessions->pluck('user_id')->unique()->count(),
                    'uniqueReaders' => $bucketSessions->pluck('user_id')->unique()->count(),
                    'minutesRead' => (int) round($bucketSessions->sum('duration_seconds') / 60),
                    'booksPublished' => $bucketBooks->count(),
                ];

                $cursor = $cursor->addDay();
            }
        }

        return [
            'data' => $points,
            'meta' => [
                'range' => $range,
                'generated_at' => now()->toIso8601String(),
                'sales_available' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getTopBooks(int $authorId, int $limit = 5): array
    {
        $books = $this->authorBooksBaseQuery($authorId)
            ->select([
                'id',
                'title',
                'status',
                'author_name',
                'cover_image_path',
                'cover_image_url',
                'created_at',
                'published_at',
                'average_rating',
                'total_reads',
            ])
            ->get();

        $bookIds = $books->pluck('id');

        $sessionCounts = DB::table('reading_sessions')
            ->select('book_id', DB::raw('COUNT(*) as reads_count'), DB::raw('COUNT(DISTINCT user_id) as unique_readers'))
            ->whereIn('book_id', $bookIds)
            ->groupBy('book_id')
            ->get()
            ->keyBy('book_id');

        $ratingStats = DB::table('book_ratings')
            ->select('book_id', DB::raw('COUNT(*) as ratings_count'), DB::raw('AVG(rating) as average_rating'))
            ->whereIn('book_id', $bookIds)
            ->groupBy('book_id')
            ->get()
            ->keyBy('book_id');

        $feedbackCounts = DB::table('book_comments')
            ->select('book_id', DB::raw('COUNT(*) as feedback_count'))
            ->whereIn('book_id', $bookIds)
            ->groupBy('book_id')
            ->pluck('feedback_count', 'book_id');

        $data = $books->map(function (object $book) use ($sessionCounts, $ratingStats, $feedbackCounts): array {
            $session = $sessionCounts->get($book->id);
            $rating = $ratingStats->get($book->id);

            return [
                'id' => (int) $book->id,
                'title' => $book->title,
                'author' => $book->author_name,
                'status' => strtolower((string) $book->status),
                'cover_image_url' => $this->resolveAssetUrl($book->cover_image_path, $book->cover_image_url),
                'totalSales' => 0,
                'totalReads' => (int) ($session->reads_count ?? $book->total_reads ?? 0),
                'uniqueReaders' => (int) ($session->unique_readers ?? 0),
                'averageRating' => round((float) ($rating->average_rating ?? $book->average_rating ?? 0), 2),
                'ratingsCount' => (int) ($rating->ratings_count ?? 0),
                'feedbackCount' => (int) ($feedbackCounts[$book->id] ?? 0),
                'publishedAt' => $book->published_at ? CarbonImmutable::parse($book->published_at)->toIso8601String() : null,
                'createdAt' => $book->created_at ? CarbonImmutable::parse($book->created_at)->toIso8601String() : null,
            ];
        })
            ->sort(function (array $left, array $right): int {
                if ($left['totalReads'] !== $right['totalReads']) {
                    return $right['totalReads'] <=> $left['totalReads'];
                }

                if ($left['uniqueReaders'] !== $right['uniqueReaders']) {
                    return $right['uniqueReaders'] <=> $left['uniqueReaders'];
                }

                if ($left['averageRating'] !== $right['averageRating']) {
                    return $right['averageRating'] <=> $left['averageRating'];
                }

                return strcmp((string) $left['title'], (string) $right['title']);
            })
            ->take(max(1, $limit))
            ->values()
            ->all();

        return [
            'data' => $data,
            'meta' => [
                'limit' => max(1, $limit),
                'generated_at' => now()->toIso8601String(),
                'sales_available' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getFeedback(int $authorId, int $limit = 5): array
    {
        $rows = DB::table('book_comments as bc')
            ->join('books as b', 'b.id', '=', 'bc.book_id')
            ->join('users as u', 'u.id', '=', 'bc.user_id')
            ->whereIn('bc.book_id', $this->authorBookIdsSubquery($authorId))
            ->orderByDesc('bc.created_at')
            ->limit(max(1, $limit))
            ->get([
                'bc.id',
                'bc.content',
                'bc.rating',
                'bc.likes_count',
                'bc.created_at',
                'b.id as book_id',
                'b.title as book_title',
                'u.id as user_id',
                'u.firstname',
                'u.lastname',
                'u.avatar',
            ]);

        return [
            'data' => $rows->map(function (object $row): array {
                return [
                    'id' => (int) $row->id,
                    'content' => $row->content,
                    'rating' => $row->rating !== null ? (int) $row->rating : null,
                    'likesCount' => (int) $row->likes_count,
                    'createdAt' => CarbonImmutable::parse($row->created_at)->toIso8601String(),
                    'book' => [
                        'id' => (int) $row->book_id,
                        'title' => $row->book_title,
                    ],
                    'reader' => [
                        'id' => (int) $row->user_id,
                        'first_name' => $row->firstname,
                        'last_name' => $row->lastname,
                        'avatar_url' => $this->resolveAvatarUrl($row->avatar),
                    ],
                ];
            })->values()->all(),
            'meta' => [
                'limit' => max(1, $limit),
                'generated_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDemographics(int $authorId): array
    {
        $readerRows = DB::table('reading_sessions as rs')
            ->join('users as u', 'u.id', '=', 'rs.user_id')
            ->whereIn('rs.book_id', $this->authorBookIdsSubquery($authorId))
            ->select('u.id', 'u.created_at')
            ->distinct()
            ->get();

        $deviceRows = DB::table('reading_sessions')
            ->whereIn('book_id', $this->authorBookIdsSubquery($authorId))
            ->select('device_type')
            ->get();

        $deviceCounts = $deviceRows
            ->groupBy(function (object $row): string {
                $value = strtolower(trim((string) $row->device_type));
                return $value !== '' ? $value : 'unknown';
            })
            ->map(fn (Collection $rows, string $device) => [
                'label' => $device,
                'count' => $rows->count(),
            ])
            ->values()
            ->all();

        $segmentCounts = [
            'new' => 0,
            'growing' => 0,
            'loyal' => 0,
        ];

        foreach ($readerRows as $reader) {
            $createdAt = CarbonImmutable::parse($reader->created_at);
            $days = $createdAt->diffInDays(CarbonImmutable::now());

            if ($days <= 30) {
                $segmentCounts['new']++;
            } elseif ($days <= 180) {
                $segmentCounts['growing']++;
            } else {
                $segmentCounts['loyal']++;
            }
        }

        $ageDistribution = $this->buildAgeDistribution($authorId);
        $regionalBreakdown = $this->buildRegionalBreakdown($authorId);

        return [
            'summary' => [
                'totalReaders' => $readerRows->count(),
                'totalDevicesTracked' => count($deviceCounts),
                'ageDataAvailable' => $ageDistribution !== [],
                'regionalDataAvailable' => $regionalBreakdown !== [],
            ],
            'devices' => $deviceCounts,
            'readerSegments' => [
                ['label' => 'new', 'count' => $segmentCounts['new']],
                ['label' => 'growing', 'count' => $segmentCounts['growing']],
                ['label' => 'loyal', 'count' => $segmentCounts['loyal']],
            ],
            'ageDistribution' => $ageDistribution,
            'regionalBreakdown' => $regionalBreakdown,
        ];
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function buildAgeDistribution(int $authorId): array
    {
        $birthColumn = $this->firstExistingUserColumn(['date_of_birth', 'birth_date', 'dob']);
        if ($birthColumn === null) {
            return [];
        }

        $rows = DB::table('reading_sessions as rs')
            ->join('users as u', 'u.id', '=', 'rs.user_id')
            ->whereIn('rs.book_id', $this->authorBookIdsSubquery($authorId))
            ->whereNotNull('u.'.$birthColumn)
            ->select('u.id', 'u.'.$birthColumn.' as birth_date')
            ->distinct()
            ->get();

        $buckets = [
            'under_18' => 0,
            '18_24' => 0,
            '25_34' => 0,
            '35_44' => 0,
            '45_54' => 0,
            '55_plus' => 0,
        ];

        foreach ($rows as $row) {
            $age = CarbonImmutable::parse($row->birth_date)->age;

            if ($age < 18) {
                $buckets['under_18']++;
            } elseif ($age <= 24) {
                $buckets['18_24']++;
            } elseif ($age <= 34) {
                $buckets['25_34']++;
            } elseif ($age <= 44) {
                $buckets['35_44']++;
            } elseif ($age <= 54) {
                $buckets['45_54']++;
            } else {
                $buckets['55_plus']++;
            }
        }

        return collect($buckets)
            ->map(fn (int $count, string $label): array => [
                'label' => $label,
                'count' => $count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function buildRegionalBreakdown(int $authorId): array
    {
        $regionColumn = $this->firstExistingUserColumn(['country', 'region', 'state', 'province', 'city', 'location']);
        if ($regionColumn === null) {
            return [];
        }

        return DB::table('reading_sessions as rs')
            ->join('users as u', 'u.id', '=', 'rs.user_id')
            ->whereIn('rs.book_id', $this->authorBookIdsSubquery($authorId))
            ->whereNotNull('u.'.$regionColumn)
            ->where('u.'.$regionColumn, '!=', '')
            ->selectRaw('u.'.$regionColumn.' as label, COUNT(DISTINCT u.id) as count')
            ->groupBy('u.'.$regionColumn)
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn (object $row): array => [
                'label' => (string) $row->label,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    private function authorBooksBaseQuery(int $authorId): Builder
    {
        $query = DB::table('books')->whereNull('deleted_at');
        $hasAuthorId = Schema::hasColumn('books', 'author_id');
        $hasUserId = Schema::hasColumn('books', 'user_id');

        if (!$hasAuthorId && !$hasUserId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $builder) use ($authorId, $hasAuthorId, $hasUserId) {
            $applied = false;

            if ($hasAuthorId) {
                $builder->where('author_id', $authorId);
                $applied = true;
            }

            if ($hasUserId) {
                if ($applied) {
                    $builder->orWhere('user_id', $authorId);
                } else {
                    $builder->where('user_id', $authorId);
                }
            }
        });
    }

    private function authorBookIdsSubquery(int $authorId): Builder
    {
        return $this->authorBooksBaseQuery($authorId)->select('id');
    }

    private function resolveAssetUrl(?string $path, ?string $url): ?string
    {
        return PublicImage::normalize($path ?: $url, 'books/covers')['url'] ?? null;
    }

    private function resolveAvatarUrl(?string $avatar): ?string
    {
        return PublicImage::normalize($avatar, 'avatars')['url'] ?? null;
    }

    private function firstExistingUserColumn(array $columns): ?string
    {
        foreach ($columns as $column) {
            if (Schema::hasColumn('users', $column)) {
                return $column;
            }
        }

        return null;
    }
}
