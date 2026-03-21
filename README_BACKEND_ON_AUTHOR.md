# Backend API Integration Guide - E-Library Admin & Author Platform

A comprehensive guide for backend developers to implement and integrate APIs with the E-Library Admin & Author frontend application.

## 📋 Table of Contents

- [Quick Start](#quick-start)
- [Project Structure](#project-structure)
- [API Documentation](#api-documentation)
- [Common Patterns](#common-patterns)
- [Request/Response Format](#requestresponse-format)
- [Authentication](#authentication)
- [Error Handling](#error-handling)
- [Performance Optimization](#performance-optimization)
- [Implementation Timeline](#implementation-timeline)
- [Development Workflow](#development-workflow)

---

## Quick Start

### For New Backend Developers

1. **Clone the repository**
   ```bash
   git clone <repo-url>
   cd e_library_admin_author
   ```

2. **Read the API Documentation** (in `/docs/`)
   - Start with the specific feature you're implementing
   - Review request/response formats
   - Check error handling requirements

3. **Implement the Endpoints**
   - Follow the exact field names and structure
   - Ensure proper HTTP status codes
   - Add appropriate database indexes

4. **Test the Integration**
   - Verify all response fields
   - Test error scenarios
   - Check response times

5. **Deploy and Monitor**
   - Set up caching as recommended
   - Monitor endpoint performance
   - Log errors and anomalies

---

## Project Structure

### Frontend Structure
```
src/
  admin/                          # Admin dashboard
    pages/
      Dashboard.jsx              # Admin home dashboard
      TopReaders.jsx             # Top readers leaderboard
      Categories.jsx             # Book categories management
      Books.jsx                  # Admin book list
      Approvals.jsx              # Book approvals workflow
      SystemMonitor.jsx          # System health monitoring
    services/
      adminService.js            # Admin API calls
    components/
      StatCard.jsx              # Stat display components
      HealthItem.jsx            # Status indicators
  author/                         # Author dashboard
    pages/
      Dashboard.jsx              # Author performance dashboard
      MyBooks.jsx               # Author's books list
    services/
      bookService.js            # Book management API calls
  lib/
    userActivityService.js       # User activity tracking
    apiClient.js                # HTTP client configuration

docs/
  dashboard-backend-guide.md
  admin-dashboard-api.md
  AUTHOR_DASHBOARD_API_GUIDE.md
  user-reading-activity-backend.md
  top-readers-backend.md
  books-backend-guide.md
  categories-backend-guide.md
  system-monitor-backend.md
  login-troubleshooting.md
  BACKEND_ENDPOINTS.md            # Quick reference
```

---

## API Documentation

### Core Feature APIs

#### 1. **Admin Dashboard** (`/admin/dashboard`)
   - **File:** `docs/dashboard-backend-guide.md`
   - **Main Endpoints:**
     - `GET /api/admin/dashboard` - Summary stats, trends, activity, health
     - `GET /api/admin/dashboard/stats` - Stats and trends only
     - `GET /api/admin/dashboard/activity` - Activity chart data
     - `GET /api/admin/dashboard/health` - System health status
   - **Features:** Summary cards, activity chart, system health monitoring

#### 2. **Author Dashboard** (`/author/dashboard`)
   - **File:** `docs/AUTHOR_DASHBOARD_API_GUIDE.md`
   - **Main Endpoints:**
     - `GET /api/author/dashboard/stats` - Total sales, readers, reads, rating
     - `GET /api/author/dashboard/performance` - Monthly performance chart
     - `GET /api/author/dashboard/top-books` - Best-performing books
     - `GET /api/author/dashboard/feedback` - Recent reader feedback
     - `GET /api/author/dashboard/demographics` - Reader demographics
   - **Features:** Performance metrics, top books, feedback, demographics

#### 3. **Top Readers** (`/admin/leaderboard`)
   - **File:** `docs/top-readers-backend.md`
   - **Main Endpoint:**
     - `GET /api/admin/leaderboard/readers` - Top readers list with ranking
   - **Features:** Leaderboard, user ranking, trend metrics

#### 4. **User Reading Activity**
   - **File:** `docs/user-reading-activity-backend.md`
   - **Main Endpoints:**
     - `POST /api/user/books/read` - Record book read
     - `PATCH /api/user/books/read/{id}` - Update reading progress
     - `GET /api/user/reading-stats` - User reading statistics
     - `GET /api/user/books/read` - Books read list
     - `GET /api/books/trending` - Trending books
   - **Features:** Activity tracking, statistics, trending data

#### 5. **Admin Books Management** (`/admin/books`)
   - **File:** `docs/books-backend-guide.md`
   - **Main Endpoints:**
     - `GET /api/admin/books` - List books with filters
     - `POST /api/admin/books/{id}/approve` - Approve book
     - `POST /api/admin/books/{id}/reject` - Reject book
   - **Features:** Book approval workflow, filtering, status tracking

#### 6. **Categories Management** (`/admin/categories`)
   - **File:** `docs/categories-backend-guide.md`
   - **Main Endpoints:**
     - `GET /api/admin/categories` - List categories
     - `POST /api/admin/categories` - Create category
   - **Features:** Category CRUD, book count tracking

#### 7. **System Monitoring** (`/admin/monitor`)
   - **File:** `docs/system-monitor-backend.md`
   - **Main Endpoints:**
     - `GET /api/admin/monitor/dashboard` - All monitoring data
     - `GET /api/admin/monitor/summary` - Summary stats
     - `GET /api/admin/monitor/activity` - Activity data
     - `GET /api/admin/monitor/health` - Health status
   - **Features:** System metrics, top books, server health

---

## Common Patterns

### 1. List Endpoints with Filters

```
GET /api/resource?search=query&status=approved&limit=50&page=1
```

**Common Query Parameters:**
- `search` - Free text search
- `status` - Filter by status (approved, pending, rejected, etc.)
- `limit` - Results per page (default: 50)
- `page` - Page number (default: 1)
- `sortBy` - Sort field (default: created_at)
- `range` - Time range (week, month, all)
- `timeRange` - Duration filter

**Response Format:**
```json
{
  "data": [...],
  "meta": {
    "total": 100,
    "page": 1,
    "perPage": 50,
    "lastPage": 2
  }
}
```

### 2. Single Resource Retrieval

```
GET /api/resource/{id}
```

**Response:**
```json
{
  "data": {
    "id": 1,
    "name": "Example",
    ...
  }
}
```

### 3. Create/Update Operations

```
POST /api/resource        // Create
PATCH /api/resource/{id}  // Update
DELETE /api/resource/{id} // Delete
```

**All mutations should return:**
```json
{
  "data": { ...updated resource },
  "message": "Resource updated successfully"
}
```

### 4. Duplicate Database Endpoints (Split vs Unified)

Some features support both unified and split endpoints:

```
# Unified endpoint (single request)
GET /api/admin/dashboard
Returns: { stats, trends, activity, health }

# Split endpoints (multiple requests, useful for detailed data)
GET /api/admin/dashboard/stats
GET /api/admin/dashboard/activity
GET /api/admin/dashboard/health
```

**Implementation Note:** Frontend tries unified first, falls back to split endpoints if needed.

---

## Request/Response Format

### HTTP Headers

**Required:**
```
Authorization: Bearer <token>
Content-Type: application/json
Accept: application/json
```

**Optional but Recommended:**
```
X-Request-ID: <uuid>  // For tracing
Accept-Language: en-US // For localization
```

### Status Codes

| Code | Meaning | Example |
|------|---------|---------|
| 200 | Success | Book fetch, stat calculation |
| 201 | Created | Book created, category added |
| 204 | No Content | Delete successful |
| 400 | Bad Request | Invalid filter parameter |
| 401 | Unauthorized | Missing/expired token |
| 403 | Forbidden | User doesn't own resource |
| 404 | Not Found | Book/user not found |
| 422 | Unprocessable Entity | Validation errors |
| 429 | Too Many Requests | Rate limited |
| 500 | Server Error | Unexpected error |

### Error Response Format

```json
{
  "status": 400,
  "message": "Validation failed",
  "errors": {
    "title": ["Title is required", "Title must be unique"],
    "author": ["Author must exist"]
  }
}
```

### Success Response Format

```json
{
  "status": 200,
  "data": { ...resource or array },
  "message": "Operation successful",
  "meta": { ...pagination or metadata }
}
```

---

## Authentication

### Token-Based Authentication

1. **Login** → Get token
   ```
   POST /api/auth/login
   { "email": "user@example.com", "password": "..." }
   Returns: { "token": "..." }
   ```

2. **Store token** → localStorage or sessionStorage
   ```javascript
   localStorage.setItem('bookhub_token', token);
   ```

3. **Use in requests** → Authorization header
   ```
   GET /api/admin/dashboard
   Authorization: Bearer {token}
   ```

4. **Token refresh** (when expired)
   ```
   POST /api/auth/refresh
   Authorization: Bearer {expired_token}
   Returns: { "token": "..." }
   ```

5. **Logout** → Invalidate token
   ```
   POST /api/auth/logout
   Authorization: Bearer {token}
   ```

### Token Validation

- Validate token signature
- Check token expiration
- Verify user still exists and is active
- Check user permissions for resource access

---

## Error Handling

### Client-Side Error Handling (Frontend)

```javascript
try {
  const response = await apiClient.get("/api/admin/dashboard");
  setData(response.data);
} catch (error) {
  if (error.response?.status === 401) {
    // Token expired, redirect to login
    navigate("/login");
  } else if (error.response?.status === 403) {
    // User doesn't have permission
    setError("You don't have permission to view this page");
  } else if (error.response?.status === 404) {
    // Resource not found
    setError("The requested resource was not found");
  } else {
    setError(error.response?.data?.message || "An error occurred");
  }
}
```

### Server-Side Error Handling (Backend)

```php
// Validation errors
throw ValidationException::withMessages([
    'email' => ['Email already exists'],
    'title' => ['Title is required']
]);
// Returns: 422 Unprocessable Entity

// Not found
throw new ModelNotFoundException("Book not found");
// Returns: 404 Not Found

// Permission denied
throw new AuthorizationException("Unauthorized");
// Returns: 403 Forbidden

// Unexpected error
throw new Exception("Database connection failed");
// Returns: 500 Internal Server Error
```

---

## Performance Optimization

### Caching Strategy

| Endpoint | TTL | Reason |
|----------|-----|--------|
| Admin dashboard stats | 5 min | Near real-time metrics |
| Author dashboard stats | 5 min | Author needs recent data |
| Performance charts | 15 min | Historical data, less volatile |
| Top books/readers | 10 min | Can change with new activity |
| System health | 1 min | Should be current |
| Reader feedback | 5 min | Recent feedback important |
| Demographics | 1 hour | Relatively stable |
| Categories | 1 day | Rarely changes |

### Database Optimization

**Essential Indexes:**
```sql
-- Users & Auth
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_status ON users(status);

-- Books
CREATE INDEX idx_books_author_id ON books(author_id);
CREATE INDEX idx_books_status ON books(status);
CREATE INDEX idx_books_category_id ON books(category_id);

-- Sales & Metrics
CREATE INDEX idx_sales_author_date ON sales(author_id, created_at);
CREATE INDEX idx_book_reads_author_date ON book_reads(author_id, created_at);
CREATE INDEX idx_reviews_book_id ON reviews(book_id);
CREATE INDEX idx_reviews_status ON reviews(status);
```

### Query Optimization

1. **Use aggregation in database**
   ```php
   // Good: Calculate in database
   $total = Sale::where('author_id', $id)->sum('amount');
   
   // Bad: Calculate in application
   $sales = Sale::where('author_id', $id)->get();
   $total = $sales->sum('amount');
   ```

2. **Eager load relationships**
   ```php
   // Good: Eager load
   $books = Book::with('author', 'category')->limit(10)->get();
   
   // Bad: N+1 queries
   $books = Book::limit(10)->get();
   foreach ($books as $book) {
       echo $book->author->name; // Separate query for each!
   }
   ```

3. **Limit result sets**
   ```php
   // Return reasonable limits
   $topBooks = Book::orderBy('sales', 'desc')->limit(4)->get();
   $feedback = Review::orderBy('created_at', 'desc')->limit(3)->get();
   ```

### Response Time Targets

| Endpoint Type | Target | With Cache |
|---------------|--------|------------|
| Single resource | < 200ms | < 50ms |
| List with filters | < 300ms | < 100ms |
| Aggregated stats | < 500ms | < 100ms |
| Charts/analytics | < 800ms | < 150ms |
| Complex queries | < 1000ms | < 200ms |

---

## Implementation Timeline

### Phase 1: Core Authentication & Books (Week 1-2)
- [ ] User login/registration
- [ ] JWT token generation
- [ ] Book CRUD operations
- [ ] Book approval workflow

### Phase 2: Admin Dashboard (Week 2-3)
- [ ] Summary stats calculation
- [ ] Activity aggregation
- [ ] System health monitoring
- [ ] Dashboard data endpoints

### Phase 3: Author Dashboard (Week 3-4)
- [ ] Author stats calculation
- [ ] Performance charts
- [ ] Top books ranking
- [ ] Demographics data

### Phase 4: Reading Activity (Week 4-5)
- [ ] Book read tracking
- [ ] User statistics
- [ ] Leaderboard aggregation
- [ ] Trending books

### Phase 5: Advanced Features (Week 5+)
- [ ] User feedback system
- [ ] Analytics & reporting
- [ ] Caching & optimization
- [ ] Monitoring & alerts

---

## Development Workflow

### Setting Up Development Environment

1. **Backend Setup**
   ```bash
   # Laravel example
   composer install
   cp .env.example .env
   php artisan key:generate
   php artisan migrate:fresh --seed
   php artisan serve
   ```

2. **Database Setup**
   ```bash
   # Create database
   mysql -u root -p < database.sql
   
   # Run migrations
   php artisan migrate
   
   # Seed test data
   php artisan db:seed
   ```

3. **API Testing**
   ```bash
   # Using Postman/Insomnia
   # Import collection from /docs/api.postman_collection.json
   
   # Or use curl
   curl -X GET "http://localhost:8000/api/admin/dashboard" \
     -H "Authorization: Bearer token123"
   ```

### Testing Flow

1. **Unit Tests** - Test individual functions
2. **Integration Tests** - Test endpoint + database
3. **API Tests** - Test full request/response
4. **Load Tests** - Test performance under load

```bash
# Run tests
php artisan test

# Run specific test
php artisan test --filter=DashboardTest

# Run with coverage
php artisan test --coverage
```

### Git Workflow

```bash
# Feature branch
git checkout -b feature/dashboard-stats

# Make changes and commit
git add .
git commit -m "feat: implement dashboard stats endpoint"

# Push and create PR
git push origin feature/dashboard-stats

# After review and approval
git merge feature/dashboard-stats
git push origin main
```

---

## Documentation Files Quick Reference

| File | Purpose | Key Sections |
|------|---------|--------------|
| `BACKEND_ENDPOINTS.md` | Quick API reference | All endpoints listed |
| `dashboard-backend-guide.md` | Admin dashboard API | Stats, activity, health |
| `AUTHOR_DASHBOARD_API_GUIDE.md` | Author dashboard API | Performance, feedback, demographics |
| `user-reading-activity-backend.md` | User activity tracking | Reading records, statistics, leaderboard |
| `top-readers-backend.md` | Leaderboard API | Reader ranking, trends |
| `books-backend-guide.md` | Book management | CRUD, approval workflow |
| `categories-backend-guide.md` | Category management | Category CRUD, counts |
| `system-monitor-backend.md` | System monitoring | Health, metrics, top books |
| `login-troubleshooting.md` | Authentication & auth issues | Login flow, error handling |

---

## Common Implementation Mistakes

### ❌ Wrong
```php
// String numbers instead of integers
return ['value' => '12840', 'sales' => '4000'];

// Missing required fields
return ['data' => ['name' => 'John']]; // Missing 'id', 'email'

// Inconsistent date formats
return ['date' => '2026-03-21', 'created_at' => '21/03/2026'];

// No error handling
return Book::all();  // Will crash if database error
```

### ✅ Correct
```php
// Proper numeric types
return ['value' => 12840, 'sales' => 4000];

// All required fields
return ['data' => ['id' => 1, 'name' => 'John', 'email' => 'john@example.com']];

// Consistent ISO 8601 dates
return ['date' => '2026-03-21T10:30:00Z'];

// Proper error handling
try {
    $books = Book::all();
    return response()->json(['data' => $books]);
} catch (Exception $e) {
    return response()->json(['error' => 'Database error'], 500);
}
```

---

## Getting Help

### Resources
- **API Documentation** - `/docs` folder
- **Code Examples** - See `src/lib/userActivityService.js` for frontend implementation patterns
- **Frontend Issues** - Check GitHub issues for reported bugs
- **Performance** - Use Laravel Debugbar or New Relic for profiling

### Common Questions

**Q: How do I handle time zones?**
A: Store all times in UTC in database, return ISO 8601 format. Frontend handles localization.

**Q: Should I return nested objects or flat?**
A: Follow the pattern in the endpoint documentation. Most use nested structure for clarity.

**Q: How do I handle pagination?**
A: Use `limit` and `page` parameters, return `meta` object with `total`, `page`, `perPage`.

**Q: Can I modify the response format?**
A: No. Follow the exact format in documentation for compatibility with frontend.

---

## Deployment Checklist

- [ ] All endpoints implemented
- [ ] All tests passing
- [ ] Database indexes created
- [ ] Cache strategy configured
- [ ] Error handling in place
- [ ] Rate limiting enabled
- [ ] CORS headers configured
- [ ] SSL certificate valid
- [ ] Monitoring set up
- [ ] Backup procedure tested
- [ ] Documentation updated
- [ ] Code reviewed
- [ ] Security audit passed
- [ ] Load testing completed
- [ ] Rollback plan documented

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-03-21 | Initial backend API guide |

---

## Support

For questions or issues:
- **Backend Support:** [backend-team@example.com]
- **Frontend Support:** [frontend-team@example.com]
- **API Issues:** [api-issues@example.com]

---

## License

This documentation is part of the E-Library Admin & Author Platform project.

---

**Last Updated:** 2026-03-21 | **Documentation Version:** 1.0
