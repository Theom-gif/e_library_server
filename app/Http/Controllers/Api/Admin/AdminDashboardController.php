<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\User;
use App\Services\AdminReaderLeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly AdminReaderLeaderboardService $leaderboardService
    ) {
    }

    public function activity(Request $request): JsonResponse
    {
        return response()->json([
            'activity' => [],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $topReadersRange = (string) $request->query('top_readers_range', 'all');
        $topReadersLimit = max(1, min((int) $request->query('top_readers_limit', 10), 100));
        $topReadersPayload = $this->leaderboardService->getLeaderboard($topReadersRange, $topReadersLimit);

        $stats = [
            'totalUsers' => User::count(),
            'totalBooks' => Book::count(),
            'pendingApprovals' => Book::where('status', 'pending')->count(),
            'authors' => User::where('role_id', 2)->count(),
        ];

        return response()->json([
            'stats' => $stats,
            'trends' => [
                'totalUsers' => 0,
                'totalBooks' => 0,
                'pendingApprovals' => 0,
                'authors' => 0,
            ],
            'activity' => [],
            'topReaders' => $topReadersPayload['data'],
            'topReadersMeta' => $topReadersPayload['meta'],
            'health' => [
                'uptimePercent' => 100,
                'apiServer' => ['status' => 'online', 'latencyMs' => 0],
                'database' => ['status' => 'online', 'queryTimeMs' => 0],
                'fileStorage' => ['status' => 'online', 'usedPercent' => 0],
                'emailService' => ['status' => 'online', 'responseMs' => 0],
            ],
        ]);
    }
}
