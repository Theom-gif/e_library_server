# Top Readers Admin Dashboard Integration Guide

This guide explains the backend contract for the **Top Readers** section on the admin dashboard.

It is based on the API that currently exists in this project, especially:

- `GET /api/admin/leaderboard/readers`
- `app/Http/Controllers/Api/AdminReaderLeaderboardController.php`
- `app/Http/Controllers/Api/Reader/ReadingSessionController.php`

## Purpose

The admin dashboard uses this feature to show which reader accounts are engaging with the library the most.

The leaderboard is currently calculated from `reading_sessions`, not from a separate `book_reads` table.

That means a reader is counted when they create reading session data through:

- `POST /api/reading-sessions/start`
- `POST /api/reading-sessions/{sessionId}/heartbeat`
- `POST /api/reading-sessions/{sessionId}/finish`

## Current Data Source

The leaderboard is built from:

- `reading_sessions.user_id`
- `reading_sessions.book_id`
- `reading_sessions.started_at`
- `users.firstname`
- `users.lastname`
- `users.email`
- `users.avatar`
- `users.created_at`

Only users with role `3` are included, which is the reader role.

## How Ranking Works

The current query ranks users by:

- `COUNT(DISTINCT rs.book_id) as books_read`

So the leaderboard counts **unique books started/read by a reader**, not:

- total session count
- total reading minutes
- total completed books only

Tie-breaking is:

1. `books_read` descending
2. `firstname` ascending

## Endpoint

### GET `/api/admin/leaderboard/readers`

Requires:

- `auth:sanctum`
- `role:admin`

### Query Parameters

| Name | Type | Allowed | Default | Notes |
| --- | --- | --- | --- | --- |
| `range` | string | `all`, `month`, `week` | `all` | Controls the time window |
| `limit` | integer | `1` to `100` | `50` | Maximum number of leaderboard rows |

### Example Request

```http
GET /api/admin/leaderboard/readers?range=month&limit=10
Authorization: Bearer <admin-token>
Accept: application/json
```

## Response Shape

Example response:

```json
{
  "data": [
    {
      "user": {
        "id": 1,
        "first_name": "Olivia",
        "last_name": "Martinez",
        "email": "olivia@example.com",
        "avatar_url": "https://example.com/storage/avatars/olivia.jpg",
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
        "email": "michael@example.com",
        "avatar_url": null,
        "created_at": "2025-04-10"
      },
      "booksRead": 142,
      "trend": 6
    }
  ],
  "meta": {
    "range": "month",
    "generated_at": "2026-03-21T09:15:00+07:00"
  }
}
```

## Field Reference

Each leaderboard row contains:

| Field | Type | Meaning |
| --- | --- | --- |
| `user.id` | integer | Reader user id |
| `user.first_name` | string | Reader first name |
| `user.last_name` | string | Reader last name |
| `user.email` | string | Reader email |
| `user.avatar_url` | string or `null` | Absolute URL if avatar exists |
| `user.created_at` | string | Account creation date in `Y-m-d` format |
| `booksRead` | integer | Distinct books read within the selected range |
| `trend` | integer | Increase compared with the previous equivalent window |

## Range Behavior

### `range=all`

- No date filter is applied
- `trend` is effectively compared against an empty previous window, so it behaves like the current count

### `range=month`

- Current window: last 1 month from now
- Previous window: the month before that
- `trend = current_month_books - previous_month_books`
- Negative values are clamped to `0`

### `range=week`

- Current window: last 7 days from now
- Previous window: the 7 days before that
- `trend = current_week_books - previous_week_books`
- Negative values are clamped to `0`

## Avatar Resolution

The controller resolves avatars like this:

- If `users.avatar` is empty, `avatar_url` is `null`
- If `users.avatar` is already an absolute `http`, `https`, or `data:` URL, it is returned as-is
- Otherwise it is converted to a public storage URL using Laravel's `public` disk

This keeps the frontend simple because it always receives a ready-to-use `avatar_url`.

## Cache Behavior

The leaderboard response is cached for **10 minutes**.

Cache key pattern:

```text
admin:leaderboard:readers:{range}:{limit}
```

Examples:

- `admin:leaderboard:readers:all:50`
- `admin:leaderboard:readers:month:10`
- `admin:leaderboard:readers:week:25`

## Relationship to Reading Activity

The leaderboard depends on `reading_sessions`, which are created and updated by the reader reading flow.

### Start Reading Session

`POST /api/reading-sessions/start`

Creates a new session for a reader and book.

Important fields:

- `book_id`
- `started_at`
- `current_page`
- `progress_percent`

### Heartbeat

`POST /api/reading-sessions/{sessionId}/heartbeat`

Adds more activity to the session and updates:

- duration
- last activity
- progress
- page position

### Finish Reading Session

`POST /api/reading-sessions/{sessionId}/finish`

Marks the session as finished and updates reading progress totals.

## Admin Dashboard Usage

For the admin dashboard, the frontend should call:

```http
GET /api/admin/leaderboard/readers?range=all&limit=10
```

Recommended dashboard variants:

- `range=all` for overall top readers
- `range=month` for monthly leaderboard
- `range=week` for short-term momentum

The backend already returns the payload pre-sorted, so the dashboard does not need extra ranking logic beyond displaying index `+ 1`.

## Recommended Frontend Mapping

The frontend can safely assume:

- `response.data` is the leaderboard array
- `response.meta.range` is the active range
- `response.meta.generated_at` is the cache generation time

Minimal example:

```javascript
const response = await api.get("/admin/leaderboard/readers", {
  params: { range: "month", limit: 10 }
});

const readers = response.data.data;

const rows = readers.map((entry, index) => ({
  rank: index + 1,
  id: entry.user.id,
  name: `${entry.user.first_name} ${entry.user.last_name}`.trim(),
  email: entry.user.email,
  avatarUrl: entry.user.avatar_url,
  booksRead: entry.booksRead,
  trend: entry.trend,
  joinedAt: entry.user.created_at
}));
```

## Validation Rules

The endpoint validates:

```php
'range' => ['nullable', Rule::in(['all', 'month', 'week'])],
'limit' => 'nullable|integer|min:1|max:100',
```

If validation fails, Laravel returns a `422 Unprocessable Entity` response.

## Error Cases

### 401 Unauthorized

Returned when the token is missing, invalid, or expired.

### 403 Forbidden

Returned when the authenticated user is not an admin.

### 422 Validation Error

Returned when:

- `range` is not one of `all`, `month`, `week`
- `limit` is below `1` or above `100`

## Practical Testing Examples

### Get overall top readers

```http
GET /api/admin/leaderboard/readers?range=all&limit=10
```

### Get top readers for the last month

```http
GET /api/admin/leaderboard/readers?range=month&limit=10
```

### Get top readers for the last week

```http
GET /api/admin/leaderboard/readers?range=week&limit=5
```

## How To Seed Useful Test Data

To make the leaderboard meaningful in development:

1. Create multiple reader users with `role_id = 3`
2. Ensure there are approved books in the database
3. Log in as each reader
4. Start reading sessions on different books
5. Send heartbeat requests
6. Finish some sessions

Because the leaderboard counts distinct books by user, a single user reading many different books will move up the ranking faster than a user creating many sessions on the same book.

## Known Limits Of The Current Implementation

The current leaderboard is intentionally simple. It does **not** yet provide:

- per-user rank endpoint
- percentile
- completed-books-only ranking
- total reading minutes ranking
- public user profile stats
- trending books from this same endpoint
- detailed user activity timeline

Those would need separate endpoints or an expanded admin analytics module.

## If You Want To Extend It Later

If the dashboard later needs deeper analytics, the safest additions would be:

1. `GET /api/admin/leaderboard/readers/rank/{userId}`
2. `GET /api/admin/leaderboard/readers/{userId}/activity`
3. add `minutesRead` to each row
4. add `completedBooks` to each row
5. add `avatar_fallback_name` for UI placeholders

## Acceptance Checklist

- [ ] Admin can call `GET /api/admin/leaderboard/readers`
- [ ] Response includes `data` and `meta`
- [ ] Each row includes `user`, `booksRead`, and `trend`
- [ ] Results are sorted descending by `booksRead`
- [ ] `range=all|month|week` works
- [ ] `limit` is enforced between `1` and `100`
- [ ] Only reader accounts are included
- [ ] Avatar URLs are usable directly by the frontend
- [ ] Response is cache-backed for admin dashboard performance
