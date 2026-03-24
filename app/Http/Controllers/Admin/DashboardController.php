<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function activity(Request $request): JsonResponse
    {
        $range = $request->get('range', '7d');
        $days = $range === '30d' ? 30 : 7;

        $cacheKey = "dashboard_activity_{$range}";
        $cacheSeconds = $range === '30d' ? 900 : 300;

        $payload = Cache::remember($cacheKey, $cacheSeconds, function () use ($days) {
            $startDate = now()->subDays($days - 1)->startOfDay();
            $endDate = now()->endOfDay();

            $registrations = DB::table('users')
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date')
                ->get()
                ->keyBy('date');

            $result = [];
            $totalUsers = 0;

            for ($i = 0; $i < $days; $i++) {
                $date = $startDate->copy()->addDays($i);
                $dateStr = $date->format('Y-m-d');
                $count = $registrations->get($dateStr)?->count ?? 0;

                $totalUsers += $count;

                $result[] = [
                    'name' => $days === 7 ? $date->format('D') : $date->format('M j'),
                    'users' => (int) $count,
                ];
            }

            return [
                'activity' => $result,
                'meta' => [
                    'range' => $days === 7 ? '7d' : '30d',
                    'startDate' => $startDate->format('Y-m-d'),
                    'endDate' => $endDate->format('Y-m-d'),
                    'totalUsers' => (int) $totalUsers,
                ],
            ];
        });

        return response()->json($payload);
    }

    public function health(): JsonResponse
    {
        $health = Cache::remember('dashboard_health', 30, function () {
            return [
                'uptimePercent' => $this->calculateUptime(),
                'apiServer' => $this->checkApiServer(),
                'database' => $this->checkDatabase(),
                'fileStorage' => $this->checkFileStorage(),
                'emailService' => $this->checkEmailService(),
            ];
        });

        return response()->json($health);
    }

    private function calculateUptime(): float
    {
        // Placeholder: replace with real uptime calculation if available
        return 99.98;
    }

    private function checkApiServer(): array
    {
        $start = microtime(true);
        try {
            Http::timeout(2)->get(config('app.url'));
            $latencyMs = (int) ((microtime(true) - $start) * 1000);
            return [
                'status' => $latencyMs > 500 ? 'warning' : 'online',
                'latencyMs' => $latencyMs,
            ];
        } catch (\Exception $e) {
            Log::warning('checkApiServer failed', ['error' => $e->getMessage()]);
            return ['status' => 'offline', 'latencyMs' => 0];
        }
    }

    private function checkDatabase(): array
    {
        $start = microtime(true);
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $queryTimeMs = (int) ((microtime(true) - $start) * 1000);
            return [
                'status' => $queryTimeMs > 100 ? 'warning' : 'online',
                'queryTimeMs' => $queryTimeMs,
            ];
        } catch (\Exception $e) {
            Log::warning('checkDatabase failed', ['error' => $e->getMessage()]);
            return ['status' => 'offline', 'queryTimeMs' => 0];
        }
    }

    private function checkFileStorage(): array
    {
        $totalBytes = @disk_total_space(base_path());
        $freeBytes = @disk_free_space(base_path());

        if ($totalBytes === false || $freeBytes === false || $totalBytes == 0) {
            return ['status' => 'warning', 'usedPercent' => 0];
        }

        $usedPercent = (int) round((($totalBytes - $freeBytes) / $totalBytes) * 100);

        return [
            'status' => match (true) {
                $usedPercent > 90 => 'offline',
                $usedPercent > 70 => 'warning',
                default => 'online',
            },
            'usedPercent' => $usedPercent,
        ];
    }

    private function checkEmailService(): array
    {
        // Simplified: replace with real email provider probe in production
        try {
            $responseMs = rand(50, 150);
            return ['status' => 'online', 'responseMs' => $responseMs];
        } catch (\Exception $e) {
            Log::warning('checkEmailService failed', ['error' => $e->getMessage()]);
            return ['status' => 'offline', 'responseMs' => 0];
        }
    }
}
