<?php

namespace App\Http\Controllers\Api\Reader;

use App\Http\Controllers\Controller;
use App\Http\Requests\FinishReadingSessionRequest;
use App\Http\Requests\HeartbeatReadingSessionRequest;
use App\Http\Requests\ReadingActivityIndexRequest;
use App\Http\Requests\StartReadingSessionRequest;
use App\Models\Book;
use App\Models\ReadingActivityDaily;
use App\Models\ReadingProgress;
use App\Models\ReadingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReadingSessionController extends Controller
{
    private const HEARTBEAT_CAP_SECONDS = 120;
    private const SESSION_IDLE_SECONDS = 120;

    public function start(StartReadingSessionRequest $request): JsonResponse
    {
        $user = $request->user();
        $book = Book::query()->findOrFail($request->integer('book_id'));

        if (!$this->canReadBook($user, $book)) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found.',
            ], 404);
        }

        $startedAt = $this->parseClientTime($request->input('started_at'));
        $currentPage = $request->integer('current_page') ?: null;
        $progressPercent = $request->input('progress_percent');

        $session = DB::transaction(function () use ($user, $book, $startedAt, $request, $currentPage, $progressPercent) {
            ReadingSession::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get()
                ->each(function (ReadingSession $activeSession) use ($startedAt) {
                    $lastActiveAt = $activeSession->last_activity_at ?? $activeSession->started_at;
                    $endedAt = $lastActiveAt && $lastActiveAt->lte($startedAt) ? $lastActiveAt : $startedAt;

                    $activeSession->forceFill([
                        'ended_at' => $endedAt,
                        'last_activity_at' => $endedAt,
                        'status' => 'inactive',
                    ])->save();
                });

            $session = ReadingSession::create([
                'book_id' => $book->id,
                'user_id' => $user->id,
                'started_at' => $startedAt,
                'ended_at' => null,
                'last_heartbeat_at' => $startedAt,
                'last_activity_at' => $startedAt,
                'duration_seconds' => 0,
                'status' => 'active',
                'start_page' => $currentPage,
                'end_page' => $currentPage,
                'last_progress_percent' => $progressPercent,
                'heartbeat_count' => 0,
                'device_type' => 'web',
                'source' => $request->input('source', 'web'),
                'is_offline' => false,
            ]);

            $activity = ReadingActivityDaily::query()->firstOrNew([
                'user_id' => $user->id,
                'activity_date' => $startedAt->toDateString(),
            ]);
            $activity->seconds_read = (int) ($activity->seconds_read ?? 0);
            $activity->minutes_read = (int) round($activity->seconds_read / 60);
            $activity->books_opened_count = (int) ($activity->books_opened_count ?? 0) + 1;
            $activity->save();

            return $session;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $session->publicId(),
                'book_id' => $session->book_id,
                'started_at' => $session->started_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function heartbeat(HeartbeatReadingSessionRequest $request, string $sessionId): JsonResponse
    {
        $session = $this->resolveOwnedSession($request->user()->id, $sessionId);
        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Reading session not found.',
            ], 404);
        }

        if ($session->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Reading session is not active.',
            ], 409);
        }

        $occurredAt = $this->parseClientTime($request->input('occurred_at'));
        $seconds = $this->resolveAcceptedSeconds($session, $request->input('seconds_since_last_ping'), $occurredAt);
        $currentPage = $request->integer('current_page') ?: $session->end_page;
        $progressPercent = $request->input('progress_percent', $session->last_progress_percent);

        $session = DB::transaction(function () use ($session, $occurredAt, $seconds, $currentPage, $progressPercent) {
            $locked = ReadingSession::query()->lockForUpdate()->findOrFail($session->id);

            if ($locked->status !== 'active') {
                return $locked;
            }

            if ($occurredAt->lte($locked->last_activity_at ?? $locked->started_at)) {
                return $locked;
            }

            $locked->forceFill([
                'duration_seconds' => $locked->duration_seconds + $seconds,
                'last_heartbeat_at' => $occurredAt,
                'last_activity_at' => $occurredAt,
                'last_progress_percent' => $progressPercent,
                'end_page' => $currentPage,
                'heartbeat_count' => $locked->heartbeat_count + 1,
            ])->save();

            $this->addDailyActivitySeconds($locked->user_id, $occurredAt, $seconds);
            $this->syncReadingProgress($locked, $occurredAt, $currentPage, $progressPercent, false, $seconds);

            return $locked->refresh();
        });

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $session->publicId(),
                'duration_seconds' => $session->duration_seconds,
                'accepted_seconds' => $seconds,
                'status' => $session->status,
            ],
        ]);
    }

    public function finish(FinishReadingSessionRequest $request, string $sessionId): JsonResponse
    {
        $session = $this->resolveOwnedSession($request->user()->id, $sessionId);
        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Reading session not found.',
            ], 404);
        }

        $endedAt = $this->parseClientTime($request->input('ended_at'));
        $progressPercent = $request->input('progress_percent', $session->last_progress_percent);
        $currentPage = $request->integer('current_page') ?: $session->end_page;

        $session = DB::transaction(function () use ($session, $endedAt, $progressPercent, $currentPage) {
            $locked = ReadingSession::query()->lockForUpdate()->findOrFail($session->id);

            $acceptedSeconds = 0;
            if ($locked->status === 'active') {
                $acceptedSeconds = $this->resolveAcceptedSeconds($locked, null, $endedAt);
                $locked->duration_seconds += $acceptedSeconds;
            }

            $finalEndedAt = ($locked->last_activity_at && $locked->last_activity_at->gt($endedAt))
                ? $locked->last_activity_at
                : $endedAt;

            $locked->forceFill([
                'ended_at' => $finalEndedAt,
                'last_activity_at' => $finalEndedAt,
                'last_heartbeat_at' => $finalEndedAt,
                'last_progress_percent' => $progressPercent,
                'end_page' => $currentPage,
                'status' => 'finished',
            ])->save();

            if ($acceptedSeconds > 0) {
                $this->addDailyActivitySeconds($locked->user_id, $finalEndedAt, $acceptedSeconds);
            }

            $this->syncReadingProgress($locked, $finalEndedAt, $currentPage, $progressPercent, true, $acceptedSeconds);

            return $locked->refresh();
        });

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $session->publicId(),
                'duration_seconds' => $session->duration_seconds,
            ],
        ]);
    }

    public function activity(ReadingActivityIndexRequest $request): JsonResponse
    {
        $user = $request->user();
        $range = $request->input('range', '7d');
        $timezone = $request->input('timezone', config('app.timezone', 'UTC'));
        $today = CarbonImmutable::now($timezone)->startOfDay();

        [$start, $end, $format, $labelFormat] = match ($range) {
            '30d' => [$today->subDays(29), $today, 'Y-m-d', 'M j'],
            '1y' => [$today->startOfMonth()->subMonths(11), $today, 'Y-m', 'M'],
            default => [$today->subDays(6), $today, 'Y-m-d', 'D'],
        };

        $records = ReadingActivityDaily::query()
            ->where('user_id', $user->id)
            ->whereBetween('activity_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('activity_date')
            ->get();

        $byDay = $records->keyBy(fn (ReadingActivityDaily $row) => $row->activity_date->toDateString());

        $data = [];
        $totalMinutes = 0;

        if ($range === '1y') {
            for ($cursor = $start; $cursor->lte($end->startOfMonth()); $cursor = $cursor->addMonth()) {
                $monthKey = $cursor->format($format);
                $minutes = $records
                    ->filter(fn (ReadingActivityDaily $row) => $row->activity_date->format('Y-m') === $monthKey)
                    ->sum(fn (ReadingActivityDaily $row) => (int) round($row->seconds_read / 60));

                $data[] = [
                    'key' => $monthKey,
                    'label' => $cursor->format($labelFormat),
                    'minutes' => $minutes,
                ];
                $totalMinutes += $minutes;
            }
        } else {
            for ($cursor = $start; $cursor->lte($end); $cursor = $cursor->addDay()) {
                $dayKey = $cursor->format($format);
                $row = $byDay->get($dayKey);
                $minutes = $row ? (int) round($row->seconds_read / 60) : 0;

                $data[] = [
                    'key' => $dayKey,
                    'label' => $cursor->format($labelFormat),
                    'minutes' => $minutes,
                ];
                $totalMinutes += $minutes;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'range' => $range,
                'unit' => 'minutes',
                'total_minutes' => $totalMinutes,
                'timezone' => $timezone,
            ],
        ]);
    }

    private function resolveOwnedSession(int $userId, string $sessionId): ?ReadingSession
    {
        $id = (int) preg_replace('/^rs_/i', '', trim($sessionId));
        if ($id < 1) {
            return null;
        }

        return ReadingSession::query()
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();
    }

    private function parseClientTime(mixed $value): CarbonImmutable
    {
        if (is_string($value) && trim($value) !== '') {
            return CarbonImmutable::parse($value);
        }

        return CarbonImmutable::now();
    }

    private function resolveAcceptedSeconds(ReadingSession $session, mixed $reportedSeconds, CarbonImmutable $occurredAt): int
    {
        $lastActivityAt = CarbonImmutable::instance($session->last_activity_at ?? $session->started_at);
        $elapsed = max(0, $lastActivityAt->diffInSeconds($occurredAt, false));
        if ($elapsed <= 0) {
            return 0;
        }

        $reported = is_numeric($reportedSeconds) ? (int) $reportedSeconds : $elapsed;
        $accepted = min($elapsed, $reported, self::HEARTBEAT_CAP_SECONDS);

        return max(0, $accepted);
    }

    private function addDailyActivitySeconds(int $userId, CarbonImmutable $occurredAt, int $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        $date = $occurredAt->toDateString();
        $row = ReadingActivityDaily::query()->firstOrNew([
            'user_id' => $userId,
            'activity_date' => $date,
        ]);

        $row->seconds_read = (int) ($row->seconds_read ?? 0) + $seconds;
        $row->minutes_read = (int) round($row->seconds_read / 60);
        $row->books_opened_count = (int) ($row->books_opened_count ?? 0);
        $row->save();
    }

    private function syncReadingProgress(
        ReadingSession $session,
        CarbonImmutable $occurredAt,
        ?int $currentPage,
        mixed $progressPercent,
        bool $incrementSessionCount,
        int $secondsToAdd
    ): void {
        $progress = ReadingProgress::query()->firstOrNew([
            'user_id' => $session->user_id,
            'book_id' => $session->book_id,
        ]);

        $existingDays = max(0, (int) ($progress->total_days ?? 0));
        $previousLastReadAt = $progress->last_read_at;
        $sameDayAsPrevious = $previousLastReadAt
            ? $previousLastReadAt->toDateString() === $occurredAt->toDateString()
            : false;

        $progress->last_page = $currentPage ?? $progress->last_page ?? 1;
        $progress->progress_percent = $progressPercent ?? $progress->progress_percent ?? 0;
        $progress->total_seconds = (int) ($progress->total_seconds ?? 0) + max(0, $secondsToAdd);
        $progress->total_sessions = (int) ($progress->total_sessions ?? 0) + ($incrementSessionCount ? 1 : 0);
        $progress->total_days = $sameDayAsPrevious ? $existingDays : $existingDays + 1;
        $progress->last_read_at = $occurredAt;

        if ((float) $progress->progress_percent >= 100) {
            $progress->completed_at = $occurredAt;
        }

        $progress->save();
    }

    private function canReadBook(?User $user, Book $book): bool
    {
        if ($book->status === 'approved') {
            return true;
        }

        if (!$user) {
            return false;
        }

        if ((int) $user->role_id === 1) {
            return true;
        }

        return (int) $user->id === (int) $book->author_id;
    }
}
