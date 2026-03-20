<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminReaderLeaderboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'range' => ['nullable', Rule::in(['all', 'month', 'week'])],
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $range = $validated['range'] ?? 'all';
        $limit = (int) ($validated['limit'] ?? 50);

        $cacheKey = "admin:leaderboard:readers:{$range}:{$limit}";

        $payload = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($range, $limit) {
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

            $data = $leaders->map(function (object $row) use ($previousCounts) {
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
            })->values();

            return [
                'data' => $data,
                'meta' => [
                    'range' => $range,
                    'generated_at' => now()->toIso8601String(),
                ],
            ];
        });

        return response()->json($payload);
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
        $value = trim((string) $avatar);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(https?:|data:)/i', $value)) {
            return $value;
        }

        return url(Storage::disk('public')->url($value));
    }
}
