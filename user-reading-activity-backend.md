# User Reading Activity & Top Readers Integration Guide

This guide explains how to integrate user reading activity tracking and the top readers leaderboard. It includes endpoints for tracking user book reads, displaying statistics, and managing the leaderboard.

## Overview

The system tracks when users read books and aggregates this data to display:
- **Top Readers Leaderboard** - Ranked by total books read
- **User Reading Stats** - Individual user reading statistics
- **Reading Activity Timeline** - User's reading history
- **Trending Books** - Books popular among readers
- **Book Analytics** - How many users have read each book

## Core Concepts

### BookRead Model

Tracks each instance a user reads a book:

```
{
  id: number
  user_id: number
  book_id: number
  status: 'started' | 'paused' | 'completed'
  progress: 0-100  // Reading progress percentage
  started_at: timestamp
  completed_at: timestamp (nullable if not completed)
  reading_time: number (seconds)
  created_at: timestamp
  updated_at: timestamp
}
```

### User Reading Stats

Aggregated statistics for a user:

```
{
  userId: number
  totalBooksRead: number       // Total completed books
  booksReadThisMonth: number   // Books completed this month
  booksReadThisWeek: number    // Books completed this week
  currentlyReading: number     // Books with progress < 100%
  trend: number                // Week-over-week change
  averageReadingTime: string   // e.g., "2.5 hours"
  longestStreak: number        // Days of consecutive reading
}
```

## API Endpoints

### User Reading Activity

#### Record Book Read
```
POST /api/user/books/read
Authorization: Bearer <token>
Content-Type: application/json

{
  "book_id": 1,
  "status": "completed | started | paused",
  "progress": 100
}

Response: 201 Created
{
  "id": 456,
  "book_id": 1,
  "status": "completed",
  "progress": 100,
  "started_at": "2026-03-20T10:00:00Z",
  "completed_at": "2026-03-20T14:30:00Z",
  "reading_time": 16200
}
```

#### Update Reading Progress
```
PATCH /api/user/books/read/{readId}
Authorization: Bearer <token>
Content-Type: application/json

{
  "progress": 45
}

Response: 200 OK
{ updated book read record }
```

### User Reading Statistics

#### Get Current User's Stats
```
GET /api/user/reading-stats
Authorization: Bearer <token>

Response: 200 OK
{
  "totalBooksRead": 45,
  "booksReadThisMonth": 8,
  "booksReadThisWeek": 2,
  "currentlyReading": 3,
  "trend": 5,
  "averageReadingTime": "2.5 hours",
  "longestStreak": 15
}
```

#### Get User's Books Read List
```
GET /api/user/books/read?status=completed&limit=50&page=1
Authorization: Bearer <token>

Query Parameters:
  status: 'completed' | 'reading' | 'all' (default: 'all')
  limit: number (default: 50)
  page: number (default: 1)

Response: 200 OK
{
  "data": [
    {
      "id": 456,
      "book": {
        "id": 1,
        "title": "Book Title",
        "author": "Author Name",
        "cover_image_url": "...",
        "category": "Fiction"
      },
      "status": "completed",
      "progress": 100,
      "startedAt": "2026-03-01T10:00:00Z",
      "completedAt": "2026-03-15T14:30:00Z",
      "readingTime": "12.5 hours"
    }
  ],
  "meta": {
    "total": 45,
    "page": 1,
    "perPage": 50
  }
}
```

#### Get Currently Reading List
```
GET /api/user/books/currently-reading?limit=10
Authorization: Bearer <token>

Query Parameters:
  limit: number (default: 10)

Response: 200 OK
{
  "data": [
    {
      "id": 789,
      "book": { ... },
      "status": "reading",
      "progress": 65,
      "startedAt": "2026-03-19T10:00:00Z",
      "readingTime": "2.5 hours"
    }
  ]
}
```

### Top Readers Leaderboard

#### Get Top Readers (Admin)
```
GET /api/admin/leaderboard/readers?range=all&limit=50
Authorization: Bearer <token>

Query Parameters:
  range: 'all' | 'month' | 'week' (default: 'all')
  limit: number (default: 50)

Response: 200 OK
{
  "data": [
    {
      "user": {
        "id": 1,
        "first_name": "Olivia",
        "last_name": "Martinez",
        "email": "olivia@example.com",
        "avatar_url": "https://...",
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
        "email": "m.brown@example.com",
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

#### Get User's Rank in Leaderboard
```
GET /api/admin/leaderboard/readers/rank/{userId}?range=all
Authorization: Bearer <token>

Query Parameters:
  range: 'all' | 'month' | 'week' (default: 'all')

Response: 200 OK
{
  "userId": 123,
  "rank": 5,
  "booksRead": 187,
  "trend": 12,
  "percentile": 95
}
```

### User Profile & Activity

#### Get User Profile
```
GET /api/users/{userId}
Authorization: Bearer <token> (optional for public profiles)

Response: 200 OK
{
  "id": 123,
  "firstName": "John",
  "lastName": "Doe",
  "email": "john@example.com",
  "avatarUrl": "...",
  "totalBooksRead": 45,
  "booksReadThisMonth": 8,
  "booksReadThisWeek": 2,
  "joinedAt": "2025-01-15"
}
```

#### Get User's Activity Timeline
```
GET /api/users/{userId}/reading-activity?timeRange=month&limit=20
Authorization: Bearer <token>

Query Parameters:
  timeRange: 'week' | 'month' | 'all' (default: 'month')
  limit: number (default: 20)

Response: 200 OK
{
  "data": [
    {
      "id": 456,
      "book": {
        "id": 1,
        "title": "Book Title",
        "author": "Author Name"
      },
      "action": "completed | started | paused",
      "timestamp": "2026-03-20T14:30:00Z",
      "readingTime": "2.5 hours"
    }
  ]
}
```

### Book Analytics

#### Get Book Read Analytics
```
GET /api/books/{bookId}/read-analytics

Response: 200 OK
{
  "bookId": 1,
  "title": "The Shadows of Time",
  "author": "Elena Thorne",
  "totalReaders": 124,
  "completionRate": 78,
  "averageReadingTime": "3.5 hours",
  "weeklyReads": 12,
  "monthlyReads": 45
}
```

#### Get Trending Books
```
GET /api/books/trending?timeRange=week&limit=10

Query Parameters:
  timeRange: 'week' | 'month' | 'all' (default: 'week')
  limit: number (default: 10)

Response: 200 OK
{
  "data": [
    {
      "bookId": 1,
      "title": "The Shadows of Time",
      "author": "Elena Thorne",
      "totalReads": 156,
      "weeklyReads": 45,
      "trend": "up",
      "coverUrl": "..."
    }
  ]
}
```

## Frontend Integration

### Using the User Activity Service

```javascript
import { 
  recordBookRead, 
  getUserReadingStats, 
  getUserBooksRead, 
  getTopReaders,
  getTrendingBooks 
} from '@/lib/userActivityService';

// Record when user finishes a book
await recordBookRead(bookId, { 
  status: 'completed', 
  progress: 100 
});

// Get user's stats
const stats = await getUserReadingStats();
console.log(`Books read: ${stats.totalBooksRead}`);

// Get top readers
const { data: readers } = await getTopReaders({ 
  range: 'month', 
  limit: 10 
});

// Get trending books
const trending = await getTrendingBooks({ 
  timeRange: 'week' 
});
```

### Components Using These APIs

1. **TopReaders.jsx** - Displays leaderboard with range filtering
2. **UserProfile.jsx** - Shows user's reading stats and profile
3. **BookDetail.jsx** - Displays book analytics
4. **Dashboard.jsx** - Shows trending books and top readers
5. **UserDashboard.jsx** - User's reading progress and stats

## Performance Considerations

### Caching Strategy

- Cache leaderboard for 5-15 minutes (query aggregation is expensive)
- Cache user stats for 1-5 minutes
- Cache book analytics for 30 minutes
- Cache trending books for 1-2 hours

### Database Indexes

Ensure these indexes exist:

```sql
CREATE INDEX idx_book_reads_user_id ON book_reads(user_id);
CREATE INDEX idx_book_reads_book_id ON book_reads(book_id);
CREATE INDEX idx_book_reads_created_at ON book_reads(created_at);
CREATE INDEX idx_book_reads_user_completed ON book_reads(user_id, status, completed_at);
```

### Query Optimization

For the top readers query:

```php
$query = BookRead::query()
  ->selectRaw('user_id, COUNT(*) as booksRead')
  ->where('status', 'completed')
  ->when($window, fn($q) => $q->where('completed_at', '>=', $window))
  ->groupBy('user_id')
  ->orderByDesc('booksRead')
  ->with('user:id,first_name,last_name,email,avatar_url,created_at')
  ->limit($limit);
```

## Error Handling

All endpoints should return:

```javascript
// Success
{
  status: 200,
  data: { ... }
}

// Client Error
{
  status: 400,
  message: "Invalid request",
  errors: { fieldName: ["error message"] }
}

// Unauthorized
{
  status: 401,
  message: "Unauthenticated",
  errors: { auth: ["Invalid or expired token"] }
}

// Server Error
{
  status: 500,
  message: "Internal Server Error"
}
```

## Testing Checklist

- [ ] Recording book reads works and aggregates correctly
- [ ] User stats update when books are marked as completed
- [ ] Leaderboard is sorted by total books read (descending)
- [ ] Range filters (week/month/all) work correctly
- [ ] User rank endpoint returns correct position
- [ ] Trending books reflect current reads
- [ ] Book analytics show accurate reader counts
- [ ] Caching strategy reduces database load
- [ ] All endpoints handle errors gracefully
- [ ] Response times are under 500ms (cached)

## Frontend Wiring Notes

The following components have been updated to use these APIs:

1. **TopReaders.jsx** - Now uses `getTopReaders()` with range filtering
2. **Dashboard.jsx** - Shows trending books and top readers
3. **SystemMonitor.jsx** - Displays reading activity trends

When implementing new features that use reading data:
- Always import from `@/lib/userActivityService`
- Handle loading and error states
- Use AbortController for cancellation
- Cache results where appropriate
- Follow the same normalization patterns
