# Admin Dashboard Data Contract (guidance for backend)

This document translates the UI needs in `src/admin/pages/Dashboard.jsx` into a precise API contract that the backend should satisfy. It complements `BACKEND_ENDPOINTS.md` and focuses on the admin home dashboard data (summary cards, activity chart, and health panel).

## Primary endpoint

```
GET /admin/dashboard
```

**Response body**

```json
{
  "stats": {
    "totalUsers": 12483,
    "totalBooks": 2847,
    "pendingApprovals": 24,
    "authors": 1234
  },
  "trends": {
    "totalUsers": 342,
    "totalBooks": 127,
    "pendingApprovals": -4,
    "authors": 89
  },
  "activity": [
    { "name": "Mon", "users": 2100 },
    { "name": "Tue", "users": 2400 }
  ],
  "health": {
    "uptimePercent": 99.98,
    "apiServer": { "status": "online", "latencyMs": 12 },
    "database": { "status": "online", "queryTimeMs": 4 },
    "fileStorage": { "status": "warning", "usedPercent": 78 },
    "emailService": { "status": "online", "responseMs": 67 }
  }
}
```

### Field notes (what the UI reads)
- `stats.*` populate the four cards (Total Users, Total Books, Pending Approvals, Authors). Use integers.
- `trends.*` are deltas versus the previous period; positive numbers show `+`, negatives show `-`.
- `activity` feeds the area chart: `name` is the X-axis label (e.g., day name or date string), `users` is the Y-axis value. Include at least 7 points for the “Last 7 days” view. Optional extra fields (e.g., `books`, `downloads`) are allowed but not required by the current chart.
- `health` drives the “System Health” list:
  - `apiServer.status`: `online | warning | offline`; `latencyMs` shown as “{latency}ms latency”.
  - `database.status`: `online | warning | offline`; `queryTimeMs` shown as “{queryTime}ms query time”.
  - `fileStorage.status`: `online | warning | offline`; `usedPercent` shown as “{usedPercent}% used”.
  - `emailService.status`: `online | warning | offline`; `responseMs` shown as “{responseMs}ms response”.
- `uptimePercent` is displayed in the green badge (e.g., `99.98` → “99.98% uptime”).

### Real data requirements (stats)
Use real database counts for the stats cards:
- `totalUsers`: total rows in `users`.
- `totalBooks`: total rows in `books`.
- `pendingApprovals`: total rows in `books` where `status = "pending"`.
- `authors`: total rows in `users` where `role_id = 2` (Author role).

### Query parameters (optional but recommended)
- `range`: `7d` (default) or `30d` to control the `activity` window. Example: `GET /admin/dashboard?range=30d`.

### HTTP semantics
- 200 with the shape above on success.
- 5xx on server errors; avoid partial payloads.
- If any subsection fails internally, prefer returning the last-known good values with an additional `meta.warning` string rather than omitting keys; missing keys break the UI.

## Optional split endpoints
If the backend prefers smaller payloads (mirrors `BACKEND_ENDPOINTS.md`):

- `GET /admin/dashboard/stats` → `{ "stats": { ... }, "trends": { ... } }`
- `GET /admin/dashboard/activity?range=7d` → `{ "activity": [ ... ] }`
- `GET /admin/dashboard/health` → `{ "health": { ... } }`

The shapes must match the primary endpoint sections.

## Data expectations & formatting
- Numbers: send as numbers (not strings). The frontend formats them.
- Labels: keep `name` short (e.g., `Mon`, `Tue`, or `2026-03-10`).
- Status values: only `online`, `warning`, `offline` are used for coloring.
- Timeouts: endpoint should respond within 8000ms (see `VITE_API_TIMEOUT_MS`).

## Testing checklist for backend
- [ ] Hitting `GET /admin/dashboard` returns all four top-level keys.
- [ ] `activity` has at least 7 points and includes `name` and `users`.
- [ ] Negative trend values show correctly (e.g., `pendingApprovals: -4`).
- [ ] Health statuses map to `online|warning|offline` and include numeric metrics.
- [ ] Requests with `?range=30d` return a longer `activity` window.

Keeping this contract stable will let the front-end replace mock data in `Dashboard.jsx` with live metrics without further UI changes.
