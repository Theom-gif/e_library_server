<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\User;
use App\Models\UserReadingLog;
use App\Services\AchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AchievementController extends Controller
{
    public function __construct(private readonly AchievementService $achievementService)
    {
    }

    public function index(): JsonResponse
    {
        $achievements = Achievement::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Achievement $achievement): array => [
                'id' => $achievement->id,
                'code' => $achievement->code,
                'title' => $achievement->title,
                'description' => $achievement->description,
                'icon' => $achievement->icon,
                'condition_type' => $achievement->condition_type,
                'condition_value' => (int) $achievement->condition_value,
            ]);

        return response()->json([
            'success' => true,
            'data' => $achievements,
        ]);
    }

    public function userAchievements(Request $request, User $user): JsonResponse
    {
        if (!$this->canAccessUser($request, $user)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to view these achievements.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->achievementService->listForUser($user->id),
        ]);
    }

    public function storeReadingLog(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'pages_read' => ['required', 'integer', 'min:1'],
            'read_date' => ['sometimes', 'date'],
        ]);

        $actor = $request->user();
        $targetUserId = isset($validated['user_id']) ? (int) $validated['user_id'] : (int) $actor->id;

        if ($actor && (int) $actor->id !== $targetUserId && (int) $actor->role_id !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to create reading logs for another user.',
            ], 403);
        }

        $log = DB::transaction(function () use ($validated, $targetUserId) {
            return UserReadingLog::query()->create([
                'user_id' => $targetUserId,
                'book_id' => (int) $validated['book_id'],
                'pages_read' => (int) $validated['pages_read'],
                'read_date' => isset($validated['read_date'])
                    ? Carbon::parse($validated['read_date'])->toDateString()
                    : now()->toDateString(),
            ]);
        });

        $newlyUnlocked = $this->achievementService->checkAchievements($targetUserId);

        return response()->json([
            'success' => true,
            'message' => 'Reading log created successfully.',
            'data' => [
                'log' => $log,
                'newly_unlocked_achievements' => $newlyUnlocked,
            ],
        ], 201);
    }

    public function checkAchievements(Request $request, User $user): JsonResponse
    {
        if (!$this->canAccessUser($request, $user)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to check these achievements.',
            ], 403);
        }

        $newlyUnlocked = $this->achievementService->checkAchievements($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Achievement check completed successfully.',
            'data' => [
                'newly_unlocked_achievements' => $newlyUnlocked,
                'achievements' => $this->achievementService->listForUser($user->id),
            ],
        ]);
    }

    private function canAccessUser(Request $request, User $user): bool
    {
        $actor = $request->user();

        if (!$actor) {
            return false;
        }

        if ((int) $actor->id === (int) $user->id) {
            return true;
        }

        return (int) $actor->role_id === 1;
    }
}
