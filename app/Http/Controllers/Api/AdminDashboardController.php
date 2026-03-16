<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
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
