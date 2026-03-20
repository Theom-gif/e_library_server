# Top Readers / Leaderboard – Backend Integration Guide

This page describes the data contract expected by the admin `TopReaders.jsx` UI and how to expose it cleanly from the backend. Use it to return real leaderboard data instead of the current mock list.

## What the frontend expects

- An array of **leader entries**, each shaped as:
  ```json
  {
    "user": {
      "id": 123,
      "first_name": "Olivia",
      "last_name": "Martinez",
      "email": "olivia@example.com",
      "avatar_url": "https://cdn.example.com/avatars/olivia.jpg",
      "created_at": "2026-01-12"
    },
    "booksRead": 187,
    "trend": 8
  }
  ```
- Sorting: entries should arrive **pre‑sorted** by `booksRead` (desc). Index 0 is rank #1, index 1 is rank #2, etc.
- `trend` is an integer delta (e.g., week‑over‑week reads). Omit or set to `0` if you don’t track it.
- `avatar_url` is optional; when absent the UI falls back to a generated avatar.

## Recommended endpoint

- `GET /api/admin/leaderboard/readers`
- Optional query params:
  - `range=all|month|week` (default: `all`)
  - `limit=50` (default: 50)

### Sample JSON response

```json
{
  "data": [
    {
      "user": {
        "id": 1,
        "first_name": "Olivia",
        "last_name": "Martinez",
        "email": "olivia.m@readinginsights.com",
        "avatar_url": "https://cdn.example.com/avatars/1.jpg",
        "created_at": "2025-03-01"
      },
      "booksRead": 187,
      "trend": 12
    },
    {
      "user": {
        "id": 2,
        "first_name": "Michael",
        "last_name": "Brown",
        "email": "m.brown@archive.edu",
        "avatar_url": null,
        "created_at": "2025-04-10"
      },
      "booksRead": 142,
      "trend": 6
    }
  ],
  "meta": {
    "range": "all",
    "generated_at": "2026-03-20T10:15:00Z"
  }
}
```

## Minimal controller logic (pseudo‑code)

```php
public function readers(Request $request)
{
    $range = $request->get('range', 'all');
    $limit = (int) $request->get('limit', 50);

    $window = match ($range) {
        'week'  => now()->subWeek(),
        'month' => now()->subMonth(),
        default => null,
    };

    $query = BookRead::query()
        ->selectRaw('user_id, COUNT(*) as booksRead')
        ->when($window, fn($q) => $q->where('created_at', '>=', $window))
        ->groupBy('user_id')
        ->orderByDesc('booksRead')
        ->limit($limit);

    $rows = $query->get();

    $data = $rows->map(function ($row) {
        $user = $row->user; // eager load users
        return [
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'created_at' => $user->created_at->toDateString(),
            ],
            'booksRead' => (int) $row->booksRead,
            'trend' => $user->weekly_delta ?? 0, // optional metric
        ];
    });

    return response()->json([
        'data' => $data,
        'meta' => [
            'range' => $range,
            'generated_at' => now()->toIso8601String(),
        ],
    ]);
}
```

## Performance & caching

- Cache the leaderboard for short windows (e.g., 5–15 minutes) to avoid heavy aggregation on every request.
- Indexes: ensure `book_reads.user_id` and `book_reads.created_at` are indexed.

## Avatars

- Provide `avatar_url` for best fidelity. If missing, the UI auto‑generates a placeholder via UI Avatars.

## Frontend wiring (FYI)

- The component currently uses mock data; once the endpoint exists, replace the `leaders` constant in `src/admin/pages/TopReaders.jsx` with data from the API. Expected field names match this document.

## Acceptance checklist

- [ ] Endpoint returns `data` array with `user`, `booksRead`, `trend`.
- [ ] Sorted descending by `booksRead`.
- [ ] Supports `range` and `limit` query params.
- [ ] Includes `created_at` (ISO date) and optional `avatar_url`.
- [ ] Responds within cached SLA (<150ms from cache, <800ms cold).
