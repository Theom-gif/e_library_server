<?php

namespace App\Services;

use App\Models\Book;
use App\Models\ReadingHistory;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationService
{
    public function notifyReadingStart(User $reader, Book $book): void
    {
        DB::transaction(function () use ($reader, $book): void {
            $history = ReadingHistory::query()->create([
                'user_id' => $reader->id,
                'book_id' => $book->id,
                'started_at' => now(),
            ]);

            $this->createNotification(
                userId: $reader->id,
                createdByUserId: $reader->id,
                role: $this->resolveRoleName($reader),
                type: 'reading.started',
                title: 'Reading Started',
                message: 'You started reading "'.$book->title.'".',
                payload: [
                    'book_id' => $book->id,
                    'history_id' => $history->id,
                    'event' => 'started',
                ]
            );

            if ($book->author_id && (int) $book->author_id !== (int) $reader->id) {
                $this->createNotification(
                    userId: (int) $book->author_id,
                    createdByUserId: $reader->id,
                    role: 'author',
                    type: 'reading.started',
                    title: 'New Reader',
                    message: $reader->firstname.' '.$reader->lastname.' started reading "'.$book->title.'".',
                    payload: [
                        'book_id' => $book->id,
                        'reader_id' => $reader->id,
                        'history_id' => $history->id,
                        'event' => 'started',
                    ]
                );
            }
        });
    }

    public function notifyReadingFinish(User $reader, Book $book): void
    {
        DB::transaction(function () use ($reader, $book): void {
            $history = ReadingHistory::query()
                ->where('user_id', $reader->id)
                ->where('book_id', $book->id)
                ->latest('id')
                ->first();

            if ($history) {
                $history->forceFill(['finished_at' => now()])->save();
            }

            $this->createNotification(
                userId: $reader->id,
                createdByUserId: $reader->id,
                role: $this->resolveRoleName($reader),
                type: 'reading.finished',
                title: 'Book Completed',
                message: 'You completed reading "'.$book->title.'".',
                payload: [
                    'book_id' => $book->id,
                    'history_id' => $history?->id,
                    'event' => 'finished',
                ]
            );

            if ($book->author_id && (int) $book->author_id !== (int) $reader->id) {
                $this->createNotification(
                    userId: (int) $book->author_id,
                    createdByUserId: $reader->id,
                    role: 'author',
                    type: 'reading.finished',
                    title: 'Book Finished',
                    message: $reader->firstname.' '.$reader->lastname.' finished reading "'.$book->title.'".',
                    payload: [
                        'book_id' => $book->id,
                        'reader_id' => $reader->id,
                        'history_id' => $history?->id,
                        'event' => 'finished',
                    ]
                );
            }
        });
    }

    public function notifyBookPublished(Book $book, User $actor): void
    {
        $admins = User::query()
            ->where('role_id', 1)
            ->get();

        foreach ($admins as $admin) {
            $this->createNotification(
                userId: $admin->id,
                createdByUserId: $actor->id,
                role: 'admin',
                type: 'book.published',
                title: 'New Book Published',
                message: 'A new book titled "'.$book->title.'" is now published.',
                payload: [
                    'book_id' => $book->id,
                    'actor_id' => $actor->id,
                    'author_id' => $book->author_id,
                ]
            );
        }
    }

    public function notifyAdminsOfAuthorRegistration(User $author): void
    {
        $admins = User::query()
            ->where('role_id', 1)
            ->get();

        $authorName = trim(($author->firstname ?? '').' '.($author->lastname ?? ''));
        $approvePath = '/api/admin/approve-authors/'.$author->id;
        $rejectPath = '/api/admin/reject-authors/'.$author->id;

        foreach ($admins as $admin) {
            $this->createNotification(
                userId: $admin->id,
                createdByUserId: $author->id,
                role: 'admin',
                type: 'author.pending_approval',
                title: 'New author request pending approval',
                message: $authorName.' requested to become an author.',
                payload: [
                    'author_id' => $author->id,
                    'authorId' => $author->id,
                    'author_name' => $authorName,
                    'authorName' => $authorName,
                    'email' => $author->email,
                    'status' => $author->status,
                    'request_type' => 'author_approval',
                    'requestType' => 'author_approval',
                    'entity_type' => 'author',
                    'entityType' => 'author',
                    'can_approve' => true,
                    'canApprove' => true,
                    'can_reject' => true,
                    'canReject' => true,
                    'approve_endpoint' => $approvePath,
                    'approveEndpoint' => $approvePath,
                    'reject_endpoint' => $rejectPath,
                    'rejectEndpoint' => $rejectPath,
                    'actions' => [
                        'approve' => [
                            'method' => 'POST',
                            'endpoint' => $approvePath,
                        ],
                        'reject' => [
                            'method' => 'POST',
                            'endpoint' => $rejectPath,
                        ],
                    ],
                ]
            );
        }
    }

    public function sendSystemNotification(string $target, string $title, string $message, ?array $payload = null, ?int $createdByUserId = null): int
    {
        $roles = $this->resolveTargets($target);
        $users = User::query()
            ->whereIn('role_id', array_keys($roles))
            ->get();

        $count = 0;

        foreach ($users as $user) {
            $this->createNotification(
                userId: $user->id,
                createdByUserId: $createdByUserId,
                role: $this->resolveRoleName($user),
                type: 'system.broadcast',
                title: $title,
                message: $message,
                payload: $payload
            );
            $count++;
        }

        return $count;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(User $user, string $role = 'user', int $perPage = 20): array
    {
        $query = UserNotification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at');

        if ($role !== 'all') {
            $query->where('role', $role);
        }

        return $query
            ->limit(max(1, min($perPage, 100)))
            ->get()
            ->map(fn (UserNotification $notification): array => $this->serializeNotification($notification))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAll(int $perPage = 20, ?string $role = null): array
    {
        $query = UserNotification::query()
            ->orderByDesc('created_at');

        if ($role !== null && $role !== 'all') {
            $query->where('role', $role);
        }

        return $query
            ->limit(max(1, min($perPage, 100)))
            ->get()
            ->map(fn (UserNotification $notification): array => $this->serializeNotification($notification))
            ->values()
            ->all();
    }

    public function unreadCount(User $user, ?string $role = null): int
    {
        $query = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('is_read', false);

        if ($role !== null && $role !== 'all') {
            $query->where('role', $role);
        }

        return (int) $query->count();
    }

    public function unreadCountAll(?string $role = null): int
    {
        $query = UserNotification::query()->where('is_read', false);

        if ($role !== null && $role !== 'all') {
            $query->where('role', $role);
        }

        return (int) $query->count();
    }

    public function markAsRead(User $user, int $notificationId): ?array
    {
        $notification = UserNotification::query()
            ->where('id', $notificationId)
            ->where('user_id', $user->id)
            ->first();

        if (!$notification) {
            return null;
        }

        if (!$notification->is_read) {
            $notification->forceFill([
                'is_read' => true,
                'read_at' => now(),
            ])->save();
        }

        return $this->serializeNotification($notification->refresh());
    }

    public function markAllAsRead(User $user, ?string $role = null): int
    {
        $query = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('is_read', false);

        if ($role !== null && $role !== 'all') {
            $query->where('role', $role);
        }

        return $query->update([
            'is_read' => true,
            'read_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeNotification(UserNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'user_id' => $notification->user_id,
            'role' => $notification->role,
            'title' => $notification->title,
            'message' => $notification->message,
            'type' => $notification->type,
            'is_read' => (bool) $notification->is_read,
            'data' => $notification->payload ?? [],
            'created_at' => $notification->created_at?->toIso8601String(),
            'read_at' => $notification->read_at?->toIso8601String(),
        ];
    }

    private function createNotification(
        int $userId,
        ?int $createdByUserId,
        string $role,
        string $type,
        string $title,
        string $message,
        ?array $payload = null
    ): UserNotification {
        return UserNotification::query()->create([
            'user_id' => $userId,
            'created_by_user_id' => $createdByUserId,
            'role' => $role,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'payload' => $payload,
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    private function resolveRoleName(User $user): string
    {
        return match ((int) $user->role_id) {
            1 => 'admin',
            2 => 'author',
            default => 'user',
        };
    }

    /**
     * @return array<int, int>
     */
    private function resolveTargets(string $target): array
    {
        return match (strtolower(trim($target))) {
            'user' => [3 => 3],
            'author' => [2 => 2],
            'admin' => [1 => 1],
            default => [1 => 1, 2 => 2, 3 => 3],
        };
    }
}
