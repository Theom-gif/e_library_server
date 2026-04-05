<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function userIndex(Request $request): JsonResponse
    {
        return $this->listResponse($request, 'user');
    }

    public function authorIndex(Request $request): JsonResponse
    {
        return $this->listResponse($request, 'author');
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (int) $user->role_id !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to view these notifications.',
            ], 403);
        }

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));
        $requestedRole = strtolower(trim((string) $request->query('role', 'admin')));
        $role = in_array($requestedRole, ['all', 'admin'], true) ? 'admin' : $requestedRole;

        return response()->json([
            'success' => true,
            'data' => $this->notificationService->listForUser($user, $role, $perPage),
            'meta' => [
                'unread_count' => $this->notificationService->unreadCount($user, $role),
                'role' => $role,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function markUserAsRead(Request $request, int $id): JsonResponse
    {
        $notification = $this->notificationService->markAsRead($request->user(), $id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'data' => $notification,
        ]);
    }

    public function markUserAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $role = strtolower(trim((string) $request->input('role', 'all')));
        $updated = $this->notificationService->markAllAsRead($user, $role);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
            'data' => [
                'updated_count' => $updated,
                'role' => $role,
            ],
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target' => ['required', 'string', 'in:all,user,author,admin'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'data' => ['nullable', 'array'],
        ]);

        $count = $this->notificationService->sendSystemNotification(
            $validated['target'],
            $validated['title'],
            $validated['message'],
            $validated['data'] ?? null,
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Notification sent successfully.',
            'data' => [
                'target' => $validated['target'],
                'recipients' => $count,
            ],
        ], 201);
    }

    private function listResponse(Request $request, string $role): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        if ($role !== 'all' && !$this->matchesRole($user, $role) && (int) $user->role_id !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to view these notifications.',
            ], 403);
        }

        $notifications = $this->notificationService->listForUser($user, $role, $perPage);

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'meta' => [
                'unread_count' => $this->notificationService->unreadCount($user, $role),
                'role' => $role,
                'per_page' => $perPage,
            ],
        ]);
    }

    private function matchesRole(User $user, string $role): bool
    {
        return match ($role) {
            'user' => (int) $user->role_id === 3,
            'author' => (int) $user->role_id === 2,
            'admin' => (int) $user->role_id === 1,
            default => false,
        };
    }
}
