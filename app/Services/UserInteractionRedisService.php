<?php

namespace App\Services;

use App\Models\Book;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class UserInteractionRedisService
{
    public function recordLogin(User $user, Request $request): void
    {
        $date = now()->toDateString();

        $this->safely(function () use ($user, $request, $date): void {
            Redis::incr('metrics:logins:total');
            Redis::hIncrBy("metrics:logins:daily:{$date}", 'total', 1);
            Redis::hIncrBy("metrics:logins:daily:{$date}", 'role:'.$this->roleLabel($user), 1);
            Redis::setEx("users:{$user->id}:last_login_ip", 604800, (string) $request->ip());
            Redis::setEx("users:{$user->id}:last_login_at", 604800, now()->toIso8601String());
        }, 'record_login', [
            'user_id' => $user->id,
        ]);
    }

    public function recordBookInteraction(Book $book, Request $request, string $action): void
    {
        $date = now()->toDateString();
        $bookId = (int) $book->id;
        $user = $request->user();

        $this->safely(function () use ($bookId, $user, $date, $action): void {
            Redis::incr("metrics:books:{$action}:total");
            Redis::incr("metrics:books:{$bookId}:{$action}");
            Redis::hIncrBy("metrics:books:{$action}:daily:{$date}", (string) $bookId, 1);
            Redis::zIncrBy("metrics:books:{$action}:top", 1, (string) $bookId);

            if ($user) {
                Redis::setEx("users:{$user->id}:last_book_{$action}", 604800, (string) $bookId);
            }
        }, 'record_book_interaction', [
            'book_id' => $bookId,
            'user_id' => $user?->id,
            'action' => $action,
        ]);
    }

    private function roleLabel(User $user): string
    {
        return match ((int) $user->role_id) {
            1 => 'admin',
            2 => 'author',
            default => 'reader',
        };
    }

    private function safely(callable $callback, string $event, array $context): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            Log::warning('Redis interaction metric failed.', [
                ...$context,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
