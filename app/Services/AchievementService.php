<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\UserAchievement;
use App\Models\UserReadingLog;
use App\Models\ReadingProgress;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AchievementService
{
    /**
     * Return every achievement with unlocked state for a specific user.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(int $userId): array
    {
        $achievements = Achievement::query()
            ->orderBy('id')
            ->get();

        $unlocked = UserAchievement::query()
            ->where('user_id', $userId)
            ->pluck('achievement_id')
            ->flip();

        return $achievements->map(function (Achievement $achievement) use ($unlocked): array {
            return $this->formatAchievement($achievement, $unlocked->has($achievement->id));
        })->all();
    }

    /**
     * Check all achievements for the user and unlock any newly earned badges.
     *
     * @return array<int, array<string, mixed>>
     */
    public function checkAchievements(int $userId): array
    {
        $achievements = Achievement::query()
            ->orderBy('id')
            ->get();

        $unlockedIds = UserAchievement::query()
            ->where('user_id', $userId)
            ->pluck('achievement_id')
            ->all();

        $newlyUnlocked = [];

        foreach ($achievements as $achievement) {
            if (in_array($achievement->id, $unlockedIds, true)) {
                continue;
            }

            if (!$this->qualifies($userId, $achievement)) {
                continue;
            }

            $this->unlock($userId, $achievement->id);
            $newlyUnlocked[] = $this->formatAchievement($achievement, true);
        }

        return $newlyUnlocked;
    }

    private function qualifies(int $userId, Achievement $achievement): bool
    {
        return match ($achievement->condition_type) {
            'COUNT' => $this->distinctBooksRead($userId) >= (int) $achievement->condition_value,
            'STREAK' => $this->currentReadingStreak($userId) >= (int) $achievement->condition_value,
            'SPEED' => $this->hasFastCompletion($userId, (int) $achievement->condition_value),
            'CATEGORY' => $this->distinctCategoriesRead($userId) >= (int) $achievement->condition_value,
            'REVIEW' => $this->reviewCount($userId) >= (int) $achievement->condition_value,
            default => false,
        };
    }

    private function distinctBooksRead(int $userId): int
    {
        return UserReadingLog::query()
            ->where('user_id', $userId)
            ->distinct()
            ->count('book_id');
    }

    private function currentReadingStreak(int $userId): int
    {
        $dates = UserReadingLog::query()
            ->where('user_id', $userId)
            ->select('read_date')
            ->distinct()
            ->orderByDesc('read_date')
            ->pluck('read_date');

        if ($dates->isEmpty()) {
            return 0;
        }

        $streak = 0;
        $expectedDate = CarbonImmutable::parse($dates->first());

        foreach ($dates as $dateValue) {
            $date = CarbonImmutable::parse($dateValue);

            if ($date->toDateString() !== $expectedDate->toDateString()) {
                break;
            }

            $streak++;
            $expectedDate = $expectedDate->subDay();
        }

        return $streak;
    }

    private function hasFastCompletion(int $userId, int $maxMinutes): bool
    {
        if ($maxMinutes <= 0) {
            return false;
        }

        return ReadingProgress::query()
            ->where('user_id', $userId)
            ->whereNotNull('completed_at')
            ->where('total_seconds', '>', 0)
            ->where('total_seconds', '<=', $maxMinutes * 60)
            ->exists();
    }

    private function distinctCategoriesRead(int $userId): int
    {
        return DB::table('user_reading_logs as url')
            ->join('books as b', 'b.id', '=', 'url.book_id')
            ->where('url.user_id', $userId)
            ->whereNotNull('b.category_id')
            ->distinct()
            ->count('b.category_id');
    }

    private function reviewCount(int $userId): int
    {
        return DB::table('book_comments')
            ->where('user_id', $userId)
            ->whereNull('parent_id')
            ->whereNotNull('rating')
            ->count();
    }

    private function unlock(int $userId, int $achievementId): void
    {
        DB::transaction(function () use ($userId, $achievementId): void {
            UserAchievement::query()->firstOrCreate(
                [
                    'user_id' => $userId,
                    'achievement_id' => $achievementId,
                ],
                [
                    'unlocked_at' => now(),
                ]
            );
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAchievement(Achievement $achievement, bool $unlocked): array
    {
        return [
            'id' => $achievement->id,
            'code' => $achievement->code,
            'title' => $achievement->title,
            'description' => $achievement->description,
            'icon' => $achievement->icon,
            'condition_type' => $achievement->condition_type,
            'condition_value' => (int) $achievement->condition_value,
            'unlocked' => $unlocked,
        ];
    }
}
