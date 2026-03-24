# Top Readers Tracking

This document describes how to compute "top readers" (users who read the most) and return the top 3 results. It includes both global and per-author variants, SQL queries, Laravel examples, caching notes, and a sample API response.

## Goals
- Count reading activity per user.
- Support site-wide and per-author leaderboards.
- Sort users from highest to lowest reading activity.
- Return the top 3 users.

## Approaches (metrics)
Choose one metric for "read a lot":
- `total_seconds` — sum of reading time (recommended).
- `total_sessions` — number of reading sessions.
- `distinct_books` — number of distinct books read.

## Database tables used
- `reading_sessions` (fields: `id`, `user_id`, `book_id`, `duration_seconds`, `created_at`, ...)
- `books` (fields: `id`, `author_id`, ...)
- optionally `reading_activity_daily` for pre-aggregated data

## 1) Top readers — global (site-wide)
SQL (total seconds):

```sql
SELECT
  rs.user_id,
  SUM(rs.duration_seconds) AS total_seconds,
  COUNT(*) AS total_sessions,
  COUNT(DISTINCT rs.book_id) AS distinct_books
FROM reading_sessions rs
WHERE rs.duration_seconds > 0
  AND rs.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) -- optional window
GROUP BY rs.user_id
ORDER BY total_seconds DESC
LIMIT 3;
```

Laravel (query builder):

```php
$top = DB::table('reading_sessions')
    ->selectRaw('user_id, SUM(duration_seconds) as total_seconds, COUNT(*) as total_sessions, COUNT(DISTINCT book_id) as distinct_books')
    ->where('duration_seconds', '>', 0)
    ->where('created_at', '>=', now()->subDays(30))
    ->groupBy('user_id')
    ->orderByDesc('total_seconds')
    ->limit(3)
    ->get();
```

## 2) Top readers — per author
To find which users read an author's books the most, join `reading_sessions` to `books` on `book_id` and filter by `books.author_id`.

SQL (per-author):

```sql
SELECT
  rs.user_id,
  SUM(rs.duration_seconds) AS total_seconds
FROM reading_sessions rs
JOIN books b ON b.id = rs.book_id
WHERE b.author_id = 42
  AND rs.duration_seconds > 0
GROUP BY rs.user_id
ORDER BY total_seconds DESC
LIMIT 3;
```

Laravel (per-author):

```php
$authorId = 42;
$topByAuthor = DB::table('reading_sessions as rs')
    ->join('books as b', 'b.id', '=', 'rs.book_id')
    ->selectRaw('rs.user_id, SUM(rs.duration_seconds) as total_seconds')
    ->where('b.author_id', $authorId)
    ->where('rs.duration_seconds', '>', 0)
    ->groupBy('rs.user_id')
    ->orderByDesc('total_seconds')
    ->limit(3)
    ->get();
```

## 3) Using pre-aggregated table (`reading_activity_daily`)
If you already aggregate per-user-per-day in `reading_activity_daily` (columns: `user_id`, `date`, `seconds_read`), query that instead for much faster results:

```sql
SELECT
  user_id,
  SUM(seconds_read) AS total_seconds
FROM reading_activity_daily
WHERE date >= '2026-03-01' AND date <= '2026-03-31'
GROUP BY user_id
ORDER BY total_seconds DESC
LIMIT 3;
```

## 4) API Response (example)
Return user metadata alongside metrics. Example JSON:

```json
{
  "top_readers": [
    { "user_id": 123, "name": "Alice", "total_seconds": 34500, "total_sessions": 120, "distinct_books": 27 },
    { "user_id": 456, "name": "Bob", "total_seconds": 28900, "total_sessions": 98, "distinct_books": 21 },
    { "user_id": 789, "name": "Carol", "total_seconds": 21500, "total_sessions": 80, "distinct_books": 17 }
  ]
}
```

In Laravel, eager-load user data and map results:

```php
$rows = /* query from above */;
$userIds = $rows->pluck('user_id')->all();
$users = User::whereIn('id', $userIds)->get()->keyBy('id');

$payload = [
    'top_readers' => $rows->map(function ($r) use ($users) {
        return [
            'user_id' => (int) $r->user_id,
            'name' => $users[$r->user_id]->full_name ?? $users[$r->user_id]->email ?? null,
            'total_seconds' => (int) $r->total_seconds,
            'total_sessions' => isset($r->total_sessions) ? (int) $r->total_sessions : null,
            'distinct_books' => isset($r->distinct_books) ? (int) $r->distinct_books : null,
        ];
    })->values(),
];

return response()->json($payload);
```

## API Endpoint

Endpoint (admin-protected):

- `GET /api/admin/leaderboard/readers`

Query parameters:
- `range` — optional: `all` (default), `month`, `week`
- `limit` — optional integer, maximum number of results (use `limit=3` for top 3)
- `author_id` — optional integer to filter leaderboard to a specific author

Authentication and permissions:
- This endpoint is mounted under the admin prefix and requires `auth:sanctum` and `role:admin` permissions.

Success response (200) — structure returned by the service:

```json
{
  "data": [
    {
      "user": {
        "id": 123,
        "first_name": "Alice",
        "last_name": "Smith",
        "email": "alice@example.com",
        "avatar_url": "https://...",
        "created_at": "2026-01-02"
      },
      "booksRead": 27,
      "trend": 5
    }
  ],
  "meta": {
    "range": "month",
    "generated_at": "2026-03-24T12:34:56Z"
  }
}
```

Examples:

- Site-wide top 3:
  - `GET /api/admin/leaderboard/readers?limit=3`
- Top 3 for an author (author id = 42):
  - `GET /api/admin/leaderboard/readers?author_id=42&limit=3`
- Last-week top 3:
  - `GET /api/admin/leaderboard/readers?range=week&limit=3`

Notes:
- The controller accepts `range`, `limit`, and `author_id` and returns `data` + `meta` as shown.
- Cache duration and limits are enforced in the service; pass `limit=3` to get the top 3.

## 5) Performance and caching
- Prefer pre-aggregated tables (`reading_activity_daily`) or materialized summaries for large datasets.
- Cache the leaderboard (e.g., 5–15 minutes) depending on freshness needs.
- Add indexes on `reading_sessions(user_id)`, `reading_sessions(book_id)`, and `books(author_id)`.

## 6) Edge cases & notes
- Exclude very short sessions (e.g., < 5 seconds) if they are noise.
- Consider deduplicating rapid repeated reads of the same book if desired.
- When multiple metrics are needed (seconds vs sessions), expose both and let frontend choose.

## 7) Summary (algorithm)
1. Aggregate reading metric per `user_id` (SUM of `duration_seconds` recommended).
2. (Optional) Filter by author via join to `books`.
3. Group by `user_id`, sort DESC by the metric.
4. Limit results to `3`.

This yields the top 3 readers according to the chosen metric.
