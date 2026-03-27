<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminReaderLeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminReaderLeaderboardController extends Controller
{
    public function __construct(
        private readonly AdminReaderLeaderboardService $leaderboardService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'range' => ['nullable', Rule::in(['all', 'month', 'week'])],
            'author_id' => 'nullable|integer|exists:books,author_id',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $range = $validated['range'] ?? 'all';
        $limit = (int) ($validated['limit'] ?? 50);
        $authorId = $validated['author_id'] ?? null;

        return response()->json(
            $this->leaderboardService->getLeaderboard($range, $limit, $authorId)
        );
    }
}
