# System Monitor – Backend Integration Guide

This guide defines the data contract the `SystemMonitor.jsx` admin UI expects so it can render live system stats, charts, and “Top Books” without mock data.

## Data blocks the UI needs

1) **Summary stats (cards)**
```json
[
  { "label": "Active Users", "value": "1,284", "change": "+12%", "trend": "up", "icon": "users", "isAlert": false },
  { "label": "Failed Logins", "value": "12", "change": "-4%", "trend": "down", "icon": "shield-alert", "isAlert": true }
]
```
- `trend`: `"up"` | `"down"`.
- `change`: string with sign and percent (e.g., `"+5%"`).
- `icon`: logical key you map to a Lucide icon client‑side (e.g., `users`, `pen-line`, `upload-cloud`, `shield-alert`).

2) **User activity (area chart)**
```json
[
  { "time": "00:00", "value": 30 },
  { "time": "04:00", "value": 45 }
]
```
- Time should be evenly spaced labels for the selected window (default 24h).

3) **Server health (bar chart)**
```json
[
  { "name": "CPU",  "cpu": 45, "ram": 60 },
  { "name": "RAM",  "cpu": 70, "ram": 40 },
  { "name": "Disk", "cpu": 30, "ram": 80 },
  { "name": "Net",  "cpu": 85, "ram": 20 }
]
```
- Values are percentages (0–100).

4) **Top books table**
```json
[
  {
    "rank": "#1",
    "title": "The Shadows of Time",
    "author": "Elena Thorne",
    "status": "Trending",
    "readers": 428,
    "coverGradient": "from-primary to-slate-900"
  }
]
```
- `status`: `"Trending" | "Popular" | "New Release" | "Steady"` → determines badge color.
- `coverGradient`: optional Tailwind gradient utility; backend may omit, the UI can default.

## Recommended endpoints

- `GET /api/admin/monitor/summary` → summary stats array.
- `GET /api/admin/monitor/activity?range=24h|7d` → activity series.
- `GET /api/admin/monitor/health` → health bars.
- `GET /api/admin/monitor/top-books?limit=5` → top books rows.

Bundle into one payload if you prefer:
`GET /api/admin/monitor/dashboard` returning:
```json
{
  "stats": [...],
  "activity": [...],
  "health": [...],
  "topBooks": [...],
  "meta": { "range": "24h", "generated_at": "2026-03-20T10:20:00Z" }
}
```

## Sample controller outline (Laravel-style pseudo-code)

```php
public function dashboard(Request $request)
{
    $range = $request->get('range', '24h');

    return response()->json([
        'stats' => $this->summaryStats(),
        'activity' => $this->activitySeries($range),
        'health' => $this->healthSnapshot(),
        'topBooks' => $this->topBooks((int) $request->get('limit', 5)),
        'meta' => [
            'range' => $range,
            'generated_at' => now()->toIso8601String(),
        ],
    ]);
}
```

### Implementation hints
- **Summary stats**: compute counts from users/books tables; “change” can be day‑over‑day or week‑over‑week deltas.
- **Activity**: aggregate sign‑ins, pageviews, or active sessions per bucket (hour for 24h, day for 7d).
- **Health**: pull from system metrics (Prometheus, Horizon, queue stats, server monitor). Clamp to 0–100.
- **Top books**: order by current readers, recent reads, or page views; return rank labels like `#1`, `#2`.

## Caching & performance
- Cache dashboard payload for 30–60 seconds to avoid heavy aggregation.
- Ensure indexes on time columns used for activity queries.

## Frontend wiring notes
- The current React component uses local mock arrays. Once the endpoint is ready, replace those arrays with API responses using the shapes defined above; property names are identical to keep the UI drop‑in compatible.
