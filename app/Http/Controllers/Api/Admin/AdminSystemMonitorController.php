<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminSystemMonitorController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'range' => 'nullable|in:24h,7d',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $range = $validated['range'] ?? '24h';
        $limit = (int) ($validated['limit'] ?? 5);

        return response()->json(Cache::remember(
            "admin:monitor:dashboard:{$range}:{$limit}",
            now()->addSeconds(60),
            fn () => [
                'stats' => $this->summaryStats(),
                'activity' => $this->activitySeries($range),
                'health' => $this->healthSnapshot(),
                'topBooks' => $this->buildTopBooks($limit),
                'meta' => [
                    'range' => $range,
                    'generated_at' => now()->toIso8601String(),
                ],
            ]
        ));
    }

    public function summary(Request $request): JsonResponse
    {
        return response()->json(
            Cache::remember('admin:monitor:summary', now()->addSeconds(60), fn () => $this->summaryStats())
        );
    }

    public function activity(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'range' => 'nullable|in:24h,7d',
        ]);

        $range = $validated['range'] ?? '24h';

        return response()->json(
            Cache::remember(
                "admin:monitor:activity:{$range}",
                now()->addSeconds(60),
                fn () => $this->activitySeries($range)
            )
        );
    }

    public function health(): JsonResponse
    {
        return response()->json(
            Cache::remember('admin:monitor:health', now()->addSeconds(30), fn () => $this->healthSnapshot())
        );
    }

    public function topBooks(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $limit = (int) ($validated['limit'] ?? 5);

        return response()->json(
            Cache::remember(
                "admin:monitor:top-books:{$limit}",
                now()->addSeconds(60),
                fn () => $this->buildTopBooks($limit)
            )
        );
    }

    private function summaryStats(): array
    {
        $now = CarbonImmutable::now();
        $activeWindowStart = $now->subMinutes(15);
        $previousActiveWindowStart = $activeWindowStart->subMinutes(15);
        $dayStart = $now->startOfDay();
        $yesterdayStart = $dayStart->subDay();
        $activeUsers = DB::table('sessions')
            ->where('last_activity', '>=', $activeWindowStart->timestamp)
            ->distinct('user_id')
            ->count('user_id');
        $previousActiveUsers = DB::table('sessions')
            ->whereBetween('last_activity', [$previousActiveWindowStart->timestamp, $activeWindowStart->timestamp - 1])
            ->distinct('user_id')
            ->count('user_id');

        $uploadsToday = DB::table('books')->where('created_at', '>=', $dayStart)->count();
        $uploadsYesterday = DB::table('books')
            ->whereBetween('created_at', [$yesterdayStart, $dayStart])
            ->count();

        $readsToday = DB::table('book_views')->where('viewed_at', '>=', $dayStart)->count();
        $readsYesterday = DB::table('book_views')
            ->whereBetween('viewed_at', [$yesterdayStart, $dayStart])
            ->count();

        $failedLogins = 0;
        $previousFailedLogins = 0;

        return [
            $this->buildStatCard('Active Users', $activeUsers, $previousActiveUsers, 'users', false),
            $this->buildStatCard('New Uploads', $uploadsToday, $uploadsYesterday, 'upload-cloud', false),
            $this->buildStatCard('Reading Events', $readsToday, $readsYesterday, 'pen-line', false),
            $this->buildStatCard('Failed Logins', $failedLogins, $previousFailedLogins, 'shield-alert', true),
        ];
    }

    private function activitySeries(string $range): array
    {
        $now = CarbonImmutable::now();

        if ($range === '7d') {
            $start = $now->startOfDay()->subDays(6);
            $rows = DB::table('book_views')
                ->selectRaw('DATE(viewed_at) as bucket, COUNT(*) as total')
                ->where('viewed_at', '>=', $start)
                ->groupByRaw('DATE(viewed_at)')
                ->pluck('total', 'bucket');

            $data = [];
            for ($cursor = $start; $cursor->lte($now->startOfDay()); $cursor = $cursor->addDay()) {
                $key = $cursor->format('Y-m-d');
                $data[] = [
                    'time' => $cursor->format('D'),
                    'value' => (int) ($rows[$key] ?? 0),
                ];
            }

            return $data;
        }

        $start = $now->startOfHour()->subHours(23);
        $rows = DB::table('book_views')
            ->selectRaw("DATE_FORMAT(viewed_at, '%Y-%m-%d %H:00:00') as bucket, COUNT(*) as total")
            ->where('viewed_at', '>=', $start)
            ->groupByRaw("DATE_FORMAT(viewed_at, '%Y-%m-%d %H:00:00')")
            ->pluck('total', 'bucket');

        $data = [];
        for ($cursor = $start; $cursor->lte($now->startOfHour()); $cursor = $cursor->addHour()) {
            $key = $cursor->format('Y-m-d H:00:00');
            $data[] = [
                'time' => $cursor->format('H:i'),
                'value' => (int) ($rows[$key] ?? 0),
            ];
        }

        return $data;
    }

    private function healthSnapshot(): array
    {
        $activeUsers = DB::table('sessions')
            ->where('last_activity', '>=', CarbonImmutable::now()->subMinutes(15)->timestamp)
            ->distinct('user_id')
            ->count('user_id');
        $recentViews = DB::table('book_views')
            ->where('viewed_at', '>=', CarbonImmutable::now()->subHour())
            ->count();

        $memoryUsagePercent = $this->memoryUsagePercent();
        $diskUsagePercent = $this->diskUsagePercent(base_path());
        $cpuPercent = min(100, max(0, $activeUsers * 5));
        $networkPercent = min(100, max(0, $recentViews));

        return [
            ['name' => 'CPU', 'cpu' => $cpuPercent, 'ram' => $memoryUsagePercent],
            ['name' => 'RAM', 'cpu' => $memoryUsagePercent, 'ram' => $diskUsagePercent],
            ['name' => 'Disk', 'cpu' => $diskUsagePercent, 'ram' => $networkPercent],
            ['name' => 'Net', 'cpu' => $networkPercent, 'ram' => $cpuPercent],
        ];
    }

    private function buildTopBooks(int $limit): array
    {
        $rows = DB::table('books as b')
            ->leftJoin('reading_sessions as rs', 'rs.book_id', '=', 'b.id')
            ->leftJoin('book_views as bv', 'bv.book_id', '=', 'b.id')
            ->selectRaw('
                b.id,
                b.title,
                COALESCE(NULLIF(b.author_name, ""), "Unknown") as author_name,
                COUNT(DISTINCT rs.user_id) as unique_readers,
                COUNT(bv.id) as view_count,
                b.created_at
            ')
            ->whereNull('b.deleted_at')
            ->groupBy('b.id', 'b.title', 'b.author_name', 'b.created_at')
            ->orderByDesc('unique_readers')
            ->orderByDesc('view_count')
            ->orderByDesc('b.created_at')
            ->limit($limit)
            ->get();

        return $rows->values()->map(function (object $row, int $index) {
            $status = $this->bookStatusLabel((int) $row->unique_readers, (int) $row->view_count, $row->created_at);

            return [
                'rank' => '#'.($index + 1),
                'title' => $row->title,
                'author' => $row->author_name ?: 'Unknown',
                'status' => $status,
                'readers' => max((int) $row->unique_readers, (int) $row->view_count),
                'coverGradient' => $this->gradientForRank($index),
            ];
        })->all();
    }

    private function buildStatCard(string $label, int $current, int $previous, string $icon, bool $isAlert): array
    {
        $delta = $current - $previous;
        $base = max(1, $previous);
        $percent = (int) round(($delta / $base) * 100);

        return [
            'label' => $label,
            'value' => number_format($current),
            'change' => sprintf('%+d%%', $percent),
            'trend' => $delta >= 0 ? 'up' : 'down',
            'icon' => $icon,
            'isAlert' => $isAlert,
        ];
    }

    private function memoryUsagePercent(): int
    {
        $limit = $this->bytesFromIni((string) ini_get('memory_limit'));
        if ($limit <= 0) {
            return 0;
        }

        return (int) min(100, round((memory_get_usage(true) / $limit) * 100));
    }

    private function diskUsagePercent(string $path): int
    {
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);

        if (!$total || $total <= 0 || $free === false) {
            return 0;
        }

        return (int) min(100, round((($total - $free) / $total) * 100));
    }

    private function bytesFromIni(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return match ($unit) {
            'g' => (int) ($number * 1024 * 1024 * 1024),
            'm' => (int) ($number * 1024 * 1024),
            'k' => (int) ($number * 1024),
            default => (int) $number,
        };
    }

    private function bookStatusLabel(int $uniqueReaders, int $viewCount, mixed $createdAt): string
    {
        $created = $createdAt ? CarbonImmutable::parse($createdAt) : null;

        if ($created && $created->gte(CarbonImmutable::now()->subDays(14))) {
            return 'New Release';
        }

        if ($uniqueReaders >= 25 || $viewCount >= 100) {
            return 'Trending';
        }

        if ($uniqueReaders >= 10 || $viewCount >= 40) {
            return 'Popular';
        }

        return 'Steady';
    }

    private function gradientForRank(int $index): string
    {
        return match ($index % 5) {
            0 => 'from-amber-500 to-rose-700',
            1 => 'from-sky-500 to-indigo-700',
            2 => 'from-emerald-500 to-teal-700',
            3 => 'from-fuchsia-500 to-violet-700',
            default => 'from-slate-500 to-slate-900',
        };
    }
}
