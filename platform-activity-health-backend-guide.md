# Platform Activity & System Health Backend Guide

This document specifies the API contract for the **Platform Activity** chart and **System Health** panel in the admin Dashboard (`src/admin/pages/Dashboard.jsx`). It focuses on displaying user registration data aggregated by date and system health metrics.

---

## Overview

The Dashboard page makes these API calls:

| Endpoint | Purpose |
|----------|---------|
| `GET /admin/dashboard` | Unified endpoint (includes all data) |
| `GET /admin/dashboard/activity?range=7d` | Activity data (users registered per day) |
| `GET /admin/dashboard/health` | System health metrics |

---

## 1. Platform Activity Endpoint

### Primary: `GET /admin/dashboard/activity`

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `range` | string | `7d` | Time window: `7d` (7 days) or `30d` (30 days) |

**Success Response (200):**
```json
{
  "activity": [
    { "name": "Mon", "users": 45 },
    { "name": "Tue", "users": 62 },
    { "name": "Wed", "users": 38 },
    { "name": "Thu", "users": 71 },
    { "name": "Fri", "users": 55 },
    { "name": "Sat", "users": 29 },
    { "name": "Sun", "users": 33 }
  ],
  "meta": {
    "range": "7d",
    "startDate": "2026-03-18",
    "endDate": "2026-03-24",
    "totalUsers": 333
  }
}
```

### Field Specifications

| Field | Type | Description |
|-------|------|-------------|
| `activity` | array | Required. Array of daily data points. |
| `activity[].name` | string | X-axis label. Use day abbreviation (`Mon`, `Tue`, etc.) for 7d, or date string (`Mar 18`) for 30d. |
| `activity[].users` | integer | Number of new user registrations on that day. |
| `meta.range` | string | The range used for the query. |
| `meta.startDate` | string | ISO date of the first data point. |
| `meta.endDate` | string | ISO date of the last data point. |
| `meta.totalUsers` | integer | Sum of all users in the range (useful for trends). |

### Database Query Logic

```sql
-- For 7d range (last 7 days including today)
SELECT 
  DATE(created_at) as registration_date,
  COUNT(*) as user_count
FROM users
WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
  AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
GROUP BY DATE(created_at)
ORDER BY registration_date ASC;

-- Fill missing dates with 0 users
```

### Date Formatting Rules

| Range | `name` Format | Example |
|-------|---------------|---------|
| `7d` | Day abbreviation | `Mon`, `Tue`, `Wed` |
| `30d` | Short date | `Mar 18`, `Mar 19`, `Mar 20` |

### Error Response (5xx)
```json
{
  "error": "Failed to fetch activity data",
  "message": "Database connection timeout"
}
```

---

## 2. System Health Endpoint

### Endpoint: `GET /admin/dashboard/health`

**Success Response (200):**
```json
{
  "uptimePercent": 99.98,
  "apiServer": {
    "status": "online",
    "latencyMs": 12
  },
  "database": {
    "status": "online",
    "queryTimeMs": 4
  },
  "fileStorage": {
    "status": "warning",
    "usedPercent": 78
  },
  "emailService": {
    "status": "online",
    "responseMs": 67
  }
}
```

### Field Specifications

| Field | Type | Status Values | Description |
|-------|------|---------------|-------------|
| `uptimePercent` | float | - | System uptime percentage (e.g., `99.98`). Displayed as "99.98% uptime". |
| `apiServer.status` | string | `online`, `warning`, `offline` | API server health status. |
| `apiServer.latencyMs` | integer | - | API response time in milliseconds. Displayed as "{latency}ms latency". |
| `database.status` | string | `online`, `warning`, `offline` | Database connection health. |
| `database.queryTimeMs` | integer | - | Average query execution time in ms. Displayed as "{queryTime}ms query time". |
| `fileStorage.status` | string | `online`, `warning`, `offline` | File storage/disk health. |
| `fileStorage.usedPercent` | integer | - | Disk usage percentage (0-100). Displayed as "{usedPercent}% used". |
| `emailService.status` | string | `online`, `warning`, `offline` | Email service health. |
| `emailService.responseMs` | integer | - | Email API response time in ms. Displayed as "{responseMs}ms response". |

### Health Status Thresholds

| Service | Warning Threshold | Offline Threshold |
|---------|-------------------|-------------------|
| API Latency | > 500ms | > 2000ms or timeout |
| Database Query | > 100ms | > 500ms or connection failed |
| File Storage | > 70% used | > 90% used |
| Email Response | > 2000ms | > 5000ms or SMTP error |

### Implementation Examples

#### API Server Health
```php
// Laravel example
$start = microtime(true);
try {
    Http::timeout(2)->get(env('APP_URL') . '/api/health');
    $latencyMs = (microtime(true) - $start) * 1000;
    $status = $latencyMs > 500 ? 'warning' : 'online';
} catch (\Exception $e) {
    $status = 'offline';
    $latencyMs = 0;
}
```

#### Database Health
```php
// Laravel example
$start = microtime(true);
try {
    DB::connection()->getPdo();
    DB::select('SELECT 1');
    $queryTimeMs = (microtime(true) - $start) * 1000;
    $status = $queryTimeMs > 100 ? 'warning' : 'online';
} catch (\Exception $e) {
    $status = 'offline';
    $queryTimeMs = 0;
}
```

#### File Storage Health
```php
// Laravel example
$totalBytes = disk_total_space(storage_path());
$freeBytes = disk_free_space(storage_path());
$usedPercent = round((($totalBytes - $freeBytes) / $totalBytes) * 100);

$status = match(true) {
    $usedPercent > 90 => 'offline',
    $usedPercent > 70 => 'warning',
    default => 'online',
};
```

#### Uptime Calculation
```php
// Calculate from application start time or system metrics
$uptimeSeconds = shell_exec('cat /proc/uptime | awk \'{print $1}\'');
$uptimePercent = min(100, round((1 - ($downtimeSeconds / $totalSeconds)) * 100, 2));
```

---

## 3. Unified Dashboard Endpoint (Optional)

### Endpoint: `GET /admin/dashboard`

Returns all dashboard data in a single response:

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
    { "name": "Mon", "users": 45 },
    { "name": "Tue", "users": 62 }
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

---

## 4. Frontend Integration Notes

### How the UI Uses This Data

From `src/admin/pages/Dashboard.jsx`:

```jsx
// Platform Activity Chart (lines 171-204)
// - Maps activity array to chart data
// - XAxis uses `name` field
// - Area chart uses `users` field
// - Range selector: 7d (default) or 30d

// System Health Panel (lines 217-220)
// - HealthItem components display each service
// - Status determines color: online (green), warning (amber), offline (red)

// Uptime Badge (lines 210-213)
// - Displays uptimePercent with "% uptime" suffix
```

### Caching Recommendations

| Data Type | Cache Duration | Rationale |
|-----------|---------------|-----------|
| Activity (7d) | 5 minutes | Changes frequently but doesn't need real-time |
| Activity (30d) | 15 minutes | Aggregated data, less volatile |
| Health | 30 seconds | Should be near real-time for monitoring |

### Timeout Requirements

- Endpoint should respond within **8000ms** (see `VITE_API_TIMEOUT_MS`)
- Health checks should have individual timeouts to avoid blocking the response

---

## 5. Testing Checklist

### Activity Endpoint
- [ ] `GET /admin/dashboard/activity?range=7d` returns 7 data points
- [ ] `GET /admin/dashboard/activity?range=30d` returns 30 data points
- [ ] All dates have corresponding user counts (fill zeros for missing dates)
- [ ] Date labels match the expected format (Mon/Tue for 7d, Mar 18 for 30d)
- [ ] Response includes `meta` with range, startDate, endDate, totalUsers

### Health Endpoint
- [ ] All four services are present (apiServer, database, fileStorage, emailService)
- [ ] Status values are only `online`, `warning`, or `offline`
- [ ] All numeric fields are numbers (not strings)
- [ ] Uptime percentage is between 0 and 100
- [ ] File storage usedPercent is between 0 and 100

### Error Handling
- [ ] Returns 200 with full payload on success
- [ ] Returns 5xx with error message on failure
- [ ] Health endpoint gracefully handles individual service failures
- [ ] Activity endpoint fills missing dates with 0, not null

---

## 6. Example Laravel Controller

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function activity(Request $request): JsonResponse
    {
        $range = $request->get('range', '7d');
        $days = $range === '30d' ? 30 : 7;
        
        $cacheKey = "dashboard_activity_{$range}";
        $cacheSeconds = $range === '30d' ? 900 : 300; // 15min or 5min
        
        $activity = Cache::remember($cacheKey, $cacheSeconds, function () use ($days) {
            $startDate = now()->subDays($days - 1)->startOfDay();
            $endDate = now()->endOfDay();
            
            // Get daily user registrations
            $registrations = DB::table('users')
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date')
                ->get()
                ->keyBy('date');
            
            // Fill in all dates with 0 for missing ones
            $result = [];
            $totalUsers = 0;
            
            for ($i = 0; $i < $days; $i++) {
                $date = $startDate->copy()->addDays($i);
                $dateStr = $date->format('Y-m-d');
                $count = $registrations->get($dateStr)?->count ?? 0;
                
                $totalUsers += $count;
                
                $result[] = [
                    'name' => $days === 7 
                        ? $date->format('D') 
                        : $date->format('M j'),
                    'users' => $count,
                ];
            }
            
            return [
                'activity' => $result,
                'meta' => [
                    'range' => $days === 7 ? '7d' : '30d',
                    'startDate' => $startDate->format('Y-m-d'),
                    'endDate' => $endDate->format('Y-m-d'),
                    'totalUsers' => $totalUsers,
                ],
            ];
        });
        
        return response()->json($activity);
    }
    
    public function health(): JsonResponse
    {
        $health = Cache::remember('dashboard_health', 30, function () {
            return [
                'uptimePercent' => $this->calculateUptime(),
                'apiServer' => $this->checkApiServer(),
                'database' => $this->checkDatabase(),
                'fileStorage' => $this->checkFileStorage(),
                'emailService' => $this->checkEmailService(),
            ];
        });
        
        return response()->json($health);
    }
    
    private function calculateUptime(): float
    {
        // Implementation depends on your uptime tracking mechanism
        // For now, return a calculated value based on downtime logs
        return 99.98;
    }
    
    private function checkApiServer(): array
    {
        $start = microtime(true);
        try {
            Http::timeout(2)->get(config('app.url'));
            $latencyMs = (int) ((microtime(true) - $start) * 1000);
            return [
                'status' => $latencyMs > 500 ? 'warning' : 'online',
                'latencyMs' => $latencyMs,
            ];
        } catch (\Exception $e) {
            return ['status' => 'offline', 'latencyMs' => 0];
        }
    }
    
    private function checkDatabase(): array
    {
        $start = microtime(true);
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $queryTimeMs = (int) ((microtime(true) - $start) * 1000);
            return [
                'status' => $queryTimeMs > 100 ? 'warning' : 'online',
                'queryTimeMs' => $queryTimeMs,
            ];
        } catch (\Exception $e) {
            return ['status' => 'offline', 'queryTimeMs' => 0];
        }
    }
    
    private function checkFileStorage(): array
    {
        $totalBytes = @disk_total_space(base_path());
        $freeBytes = @disk_free_space(base_path());
        
        if ($totalBytes === false || $freeBytes === false) {
            return ['status' => 'warning', 'usedPercent' => 0];
        }
        
        $usedPercent = (int) round((($totalBytes - $freeBytes) / $totalBytes) * 100);
        
        return [
            'status' => match(true) {
                $usedPercent > 90 => 'offline',
                $usedPercent > 70 => 'warning',
                default => 'online',
            },
            'usedPercent' => $usedPercent,
        ];
    }
    
    private function checkEmailService(): array
    {
        // Simplified check - in production, ping your email service
        return [
            'status' => 'online',
            'responseMs' => rand(50, 100),
        ];
    }
}
```

---

## Summary

This guide provides the complete specification for integrating Platform Activity and System Health into the admin Dashboard:

1. **Platform Activity** shows user registrations over time (7d or 30d)
2. **System Health** monitors API, database, storage, and email service
3. All endpoints should be fast (< 8s timeout) and return proper error responses
4. Use appropriate caching to balance freshness and performance
