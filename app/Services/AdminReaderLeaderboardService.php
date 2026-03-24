<?php

namespace App\Services;

use App\Support\PublicImage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminReaderLeaderboardService
{
    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function getLeaderboard(string $range = 'all', int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));
        $range = in_array($range, ['all', 'month', 'week'], true) ? $range : 'all';

        $cacheKey = "admin:leaderboard:readers:{$range}:{$limit}";

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($range, $limit) {
            $currentWindowStart = match ($range) {
                'week' => CarbonImmutable::now()->subWeek(),
                'month' => CarbonImmutable::now()->subMonth(),
                default => null,
            };

            $previousWindowStart = match ($range) {
                'week' => CarbonImmutable::now()->subWeeks(2),
                'month' => CarbonImmutable::now()->subMonths(2),
                default => null,
            };

            $previousWindowEnd = match ($range) {
                'week' => CarbonImmutable::now()->subWeek(),
                'month' => CarbonImmutable::now()->subMonth(),
                default => null,
            };

            $leaders = $this->buildLeaderboard($currentWindowStart, null, $limit);
            $previousCounts = $this->buildPreviousCounts($previousWindowStart, $previousWindowEnd);

            $data = $leaders->map(function (object $row) use ($previousCounts): array {
                $previous = (int) ($previousCounts[$row->user_id] ?? 0);
                $current = (int) $row->books_read;

                return [
                    'user' => [
                        'id' => (int) $row->user_id,
                        'first_name' => $row->firstname,
                        'last_name' => $row->lastname,
                        'email' => $row->email,
                        'avatar_url' => $this->resolveAvatarUrl($row->avatar),
                        'created_at' => optional($row->created_at)?->format('Y-m-d'),
                    ],
                    'booksRead' => $current,
                    'trend' => max(0, $current - $previous),
                ];
            })->values()->all();

            return [
                'data' => $data,
                'meta' => [
                    'range' => $range,
                    'generated_at' => now()->toIso8601String(),
                ],
            ];
        });
    }

    private function buildLeaderboard(?CarbonImmutable $from, ?CarbonImmutable $to, int $limit): Collection
    {
        return DB::table('reading_sessions as rs')
            ->join('users as u', 'u.id', '=', 'rs.user_id')
            ->selectRaw('
                rs.user_id,
                u.firstname,
                u.lastname,
                u.email,
                u.avatar,
                u.created_at,
                COUNT(DISTINCT rs.book_id) as books_read
            ')
            ->where('u.role_id', 3)
            ->when($from, fn ($query) => $query->where('rs.started_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('rs.started_at', '<', $to))
            ->groupBy('rs.user_id', 'u.firstname', 'u.lastname', 'u.email', 'u.avatar', 'u.created_at')
            ->orderByDesc('books_read')
            ->orderBy('u.firstname')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<int, int>
     */
    private function buildPreviousCounts(?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        if (!$from || !$to) {
            return [];
        }

        return DB::table('reading_sessions as rs')
            ->join('users as u', 'u.id', '=', 'rs.user_id')
            ->selectRaw('rs.user_id, COUNT(DISTINCT rs.book_id) as books_read')
            ->where('u.role_id', 3)
            ->where('rs.started_at', '>=', $from)
            ->where('rs.started_at', '<', $to)
            ->groupBy('rs.user_id')
            ->pluck('books_read', 'user_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    private function resolveAvatarUrl(?string $avatar): ?string
    {
        return PublicImage::normalize($avatar, 'avatars')['url'] ?? null;
    }
}
