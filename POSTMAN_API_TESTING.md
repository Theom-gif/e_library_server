# API Testing Guide

This document is a practical Postman guide for testing the current backend API defined in `routes/api.php`.

All endpoints below assume Laravel's `/api` prefix.

## Base URL

Use one of these values for a Postman environment variable named `base_url`:

- Local: `http://localhost:8000/api`
- Hosted: `https://elibrary.pncproject.site/api`

## Recommended Postman Environment

Create these variables before testing:

| Variable | Example | Purpose |
| --- | --- | --- |
| `base_url` | `http://localhost:8000/api` | API root |
| `token` | empty | Bearer token from login |
| `reader_email` | `reader@example.com` | Reader login |
| `reader_password` | `SecurePass123!` | Reader password |
| `author_email` | `author@example.com` | Author login |
| `author_password` | `SecurePass123!` | Author password |
| `admin_email` | `admin@example.com` | Admin login |
| `admin_password` | `SecurePass123!` | Admin password |
| `book_id` | empty | Saved from book responses |
| `category_id` | empty | Saved from category responses |
| `user_id` | empty | Saved from admin user responses |
| `session_id` | empty | Saved from reading session start |

## Common Headers

For JSON requests:

```http
Accept: application/json
Content-Type: application/json
```

For protected routes:

```http
Authorization: Bearer {{token}}
```

For file upload requests, use Postman `form-data` and do not set `Content-Type` manually.

## Response Patterns

Most endpoints return one of these shapes:

Success:

```json
{
  "success": true,
  "message": "Operation successful",
  "data": {}
}
```

Validation or error:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {}
}
```

Some older endpoints like `posts`, `admin/books`, and `admin/categories` return slightly different shapes, so check the examples below per route.

## Suggested Collection Folders

1. Health
2. Authentication
3. Categories
4. Public Books
5. Reader Features
6. Reading Sessions
7. Author Features
8. Admin Features
9. Posts

## 1. Health

### GET `{{base_url}}/health`

Expected:

- `200 OK`
- JSON contains `status: healthy`

Postman test:

```javascript
pm.test("Health endpoint works", function () {
    pm.response.to.have.status(200);
    const json = pm.response.json();
    pm.expect(json.status).to.eql("healthy");
});
```

## 2. Authentication

### POST `{{base_url}}/auth/register`

Register a normal reader user.

```json
{
  "firstname": "Reader",
  "lastname": "Tester",
  "email": "reader@example.com",
  "password": "SecurePass123!",
  "password_confirmation": "SecurePass123!",
  "role_id": 3
}
```

Expected:

- `201 Created`
- `data.user`
- `data.token`

### POST `{{base_url}}/auth/author_registration`

Register an author account.

```json
{
  "firstname": "Author",
  "lastname": "Tester",
  "email": "author@example.com",
  "password": "SecurePass123!",
  "password_confirmation": "SecurePass123!"
}
```

Notes:

- If `role_id` is omitted, the request class is intended for author signup.

### POST `{{base_url}}/auth/login`

```json
{
  "email": "{{reader_email}}",
  "password": "{{reader_password}}"
}
```

Postman test to save token:

```javascript
pm.test("Login succeeded", function () {
    pm.response.to.have.status(200);
});

const json = pm.response.json();
const token = json.data?.token;

if (token) {
    pm.environment.set("token", token);
}
```

### GET `{{base_url}}/user`

Protected route. Returns the authenticated user.

Expected:

- `200 OK`
- `data.id`, `data.email`, `data.role_id`

### GET `{{base_url}}/auth/me`

Protected route. Also returns the authenticated user.

### GET `{{base_url}}/me`

Protected route. Returns a profile-style payload with:

- `data.user`
- `data.stats`

### GET `{{base_url}}/me/profile`

Protected route. Same profile payload as `/me`.

### PATCH `{{base_url}}/auth/update-profile`

```json
{
  "firstname": "Updated",
  "lastname": "Reader",
  "bio": "Updated from Postman",
  "facebook_url": "https://facebook.com/updated.reader"
}
```

### PATCH `{{base_url}}/me/profile`

Same idea as `auth/update-profile`, but returns the profile payload format.

### POST `{{base_url}}/auth/change-password`

```json
{
  "current_password": "SecurePass123!",
  "new_password": "NewSecurePass456!",
  "new_password_confirmation": "NewSecurePass456!"
}
```

Expected:

- `200 OK`
- Password updated

### POST `{{base_url}}/auth/request-password-reset`

```json
{
  "email": "{{reader_email}}"
}
```

Expected:

- `200 OK`
- Reset token is stored in `password_reset_tokens`

Important:

- Email sending is not implemented yet.
- For local testing, you must read the reset token from the database or seed/mocking flow.

### POST `{{base_url}}/auth/reset-password`

```json
{
  "email": "{{reader_email}}",
  "token": "RESET_TOKEN_FROM_DB",
  "password": "AnotherSecure789!",
  "password_confirmation": "AnotherSecure789!"
}
```

### POST `{{base_url}}/logout`

Protected route. Revokes the current token.

### POST `{{base_url}}/auth/logout`

Protected route. Alias for logout.

## 3. Categories

### GET `{{base_url}}/categories`

Public route. Returns active categories by default.

Useful query params:

- `active_only=0` to include inactive categories

Aliases:

- `GET {{base_url}}/category`
- `GET {{base_url}}/categories/all`
- `GET {{base_url}}/author/categories`
- `GET {{base_url}}/author/books/categories`

### POST `{{base_url}}/categories`

Public resource route. Use if you want to test the general category CRUD.

```json
{
  "name": "Science Fiction",
  "description": "Books about future worlds",
  "is_active": true
}
```

Save category id:

```javascript
const json = pm.response.json();
const id = json.data?.id;
if (id) {
    pm.environment.set("category_id", id);
}
```

### GET `{{base_url}}/categories/{{category_id}}`

### PATCH `{{base_url}}/categories/{{category_id}}`

```json
{
  "name": "Science and Technology",
  "description": "Updated category"
}
```

### DELETE `{{base_url}}/categories/{{category_id}}`

## 4. Public Books

### GET `{{base_url}}/books`

Public approved books listing.

Useful query params:

- `per_page=10`
- `search=history`
- `category_id={{category_id}}`
- `sort=newest`
- `sort=title&order=asc`

Aliases:

- `GET {{base_url}}/book`

Save a book id:

```javascript
const json = pm.response.json();
const bookId = json.data?.[0]?.id || json.books?.[0]?.id || json.results?.[0]?.id;
if (bookId) {
    pm.environment.set("book_id", bookId);
}
```

### GET `{{base_url}}/books/discover?search=harry`

Searches local approved books and optionally Open Library.

Useful query params:

- `search=harry`
- `include_external=true`
- `local_limit=5`
- `external_limit=5`

Expected:

- `data.local`
- `data.external`
- `meta.external_error` when the external service fails

### GET `{{base_url}}/books/{{book_id}}`

Returns one book. Public users can only access approved books.

### GET `{{base_url}}/books/{{book_id}}/read`

Returns the PDF file itself if available.

Expected:

- `200 OK` with file response
- or `404` if file missing

### GET `{{base_url}}/books/{{book_id}}/cover`

Returns either:

- image file response
- JSON with `cover_url`
- or `404`

### POST `{{base_url}}/books/{{book_id}}/download`

Returns a resolved download payload.

Expected fields:

- `download_url`
- `stream_url`
- `data.file_name`
- `data.mime_type`

Note:

- If authenticated, this also records the offline download.

## 5. Reader Features

These routes require a valid Bearer token.

### GET `{{base_url}}/books/{{book_id}}/comments`

List comments for an approved book.

### POST `{{base_url}}/books/{{book_id}}/comments`

Add a comment to an approved book.

```json
{
  "content": "This is a solid read."
}
```

Optional reply example:

```json
{
  "content": "I agree with this point.",
  "parent_id": 1
}
```

Expected:

- `201 Created`

### GET `{{base_url}}/books/{{book_id}}/ratings`

Returns:

- `average_rating`
- `total_ratings`
- `distribution`
- `user_rating` when authenticated

Aliases:

- `GET {{base_url}}/books/{{book_id}}/rating`
- `GET {{base_url}}/ratings/{{book_id}}`

### POST `{{base_url}}/books/{{book_id}}/ratings`

```json
{
  "rating": 5
}
```

Expected:

- `200 OK`
- Creates or updates the current user's rating

Alias:

- `POST {{base_url}}/books/{{book_id}}/rating`

### GET `{{base_url}}/favorites`

Lists the current user's favorite books.

### POST `{{base_url}}/favorites`

```json
{
  "book_id": {{book_id}}
}
```

Expected:

- `201 Created` on first add
- `200 OK` with `Already in favorites.` if repeated

### DELETE `{{base_url}}/favorites/{{book_id}}`

Removes the favorite by book id.

### GET `{{base_url}}/downloads`

Lists recorded offline downloads for the current user.

### POST `{{base_url}}/books/{{book_id}}/downloads`

Records an offline download event.

```json
{
  "local_identifier": "device-copy-001"
}
```

Expected:

- `201 Created`
- `data.id`
- `data.book_id`

## 6. Reading Sessions

These routes require a valid Bearer token.

### POST `{{base_url}}/reading-sessions/start`

```json
{
  "book_id": {{book_id}},
  "started_at": "2026-03-21T10:00:00Z",
  "current_page": 1,
  "progress_percent": 0,
  "source": "web"
}
```

Expected:

- `201 Created`
- `data.session_id`

Save session id:

```javascript
const json = pm.response.json();
const sessionId = json.data?.session_id;
if (sessionId) {
    pm.environment.set("session_id", sessionId);
}
```

### POST `{{base_url}}/reading-sessions/{{session_id}}/heartbeat`

```json
{
  "occurred_at": "2026-03-21T10:02:00Z",
  "seconds_since_last_ping": 120,
  "current_page": 5,
  "progress_percent": 12
}
```

Expected:

- `200 OK`
- `data.accepted_seconds`
- `data.duration_seconds`

### POST `{{base_url}}/reading-sessions/{{session_id}}/finish`

```json
{
  "ended_at": "2026-03-21T10:10:00Z",
  "current_page": 12,
  "progress_percent": 25
}
```

Expected:

- `200 OK`
- `data.duration_seconds`

### GET `{{base_url}}/me/reading-activity`

Useful query params:

- `range=7d`
- `range=30d`
- `range=1y`
- `timezone=Asia/Bangkok`

Expected:

- `data[]` with daily or monthly minute totals
- `meta.total_minutes`

## 7. Author Features

These routes require:

- valid Bearer token
- authenticated user with role `author`

### GET `{{base_url}}/auth/books`

Lists books managed by the author/admin legacy controller.

### GET `{{base_url}}/auth/books/{{book_id}}`

### POST `{{base_url}}/auth/book`

Legacy author create route.

### POST `{{base_url}}/author/books`

Primary author upload route.

Body type: `form-data`

Required fields:

- `title` as text
- `category` as text, or `category_id` as text
- `pdf` as file

Optional fields:

- `description`
- `author_name`
- `language`
- `total_pages`
- `cover_image` as file

Accepted upload aliases:

- PDF aliases: `manuscript`, `book_pdf`, `book_file`, `bookFile`, `pdfFile`
- Cover aliases: `cover`, `thumbnail`, `image`, `coverImage`
- Category alias: `categoryId`
- Author alias: `authorName`
- Total pages alias: `totalPages`

Recommended sample:

- `title`: `Postman Upload Test`
- `category`: `Science Fiction`
- `description`: `Uploaded from Postman`
- `language`: `en`
- `total_pages`: `120`
- `pdf`: attach a PDF file
- `cover_image`: attach an image file

Expected:

- `201 Created`
- uploaded book status is `pending`

Aliases:

- `POST {{base_url}}/author/books/upload`
- `POST {{base_url}}/author/upload`
- `POST {{base_url}}/author/books/create`

### GET `{{base_url}}/author/books`

Lists the current author's books.

Useful query params:

- `status=approved`
- `status=pending`
- `status=rejected`
- `status=all`
- `search=test`
- `category_id={{category_id}}`

### GET `{{base_url}}/author/books/search?search=history`

Alias of author list/search behavior.

### GET `{{base_url}}/author/research?search=history`

Author-only research endpoint. Searches the author's local books and optionally Open Library.

Useful query params:

- `search=history`
- `status=pending`
- `include_external=true`
- `local_limit=10`
- `external_limit=5`

Aliases:

- `GET {{base_url}}/author/search`
- `GET {{base_url}}/author/books/research`

### GET `{{base_url}}/author/books/{{book_id}}`

Authors can view their own non-approved books.

### GET `{{base_url}}/author/books/{{book_id}}/read`

### GET `{{base_url}}/author/books/{{book_id}}/cover`

### PATCH `{{base_url}}/auth/books/{{book_id}}`

Legacy author update route.

If Postman has trouble with `PATCH` plus multipart, use `POST` and include `_method=PATCH`.

### DELETE `{{base_url}}/auth/books/{{book_id}}`

Legacy author delete route.

## 8. Admin Features

These routes require:

- valid Bearer token
- authenticated user with role `admin`

### GET `{{base_url}}/admin/dashboard`

Returns dashboard stats such as:

- `stats.totalUsers`
- `stats.totalBooks`
- `stats.pendingApprovals`
- `stats.authors`

### GET `{{base_url}}/admin/dashboard/activity`

Currently returns:

```json
{
  "activity": []
}
```

### GET `{{base_url}}/admin/leaderboard/readers`

Useful query params:

- `range=all`
- `range=month`
- `range=week`
- `limit=10`

### GET `{{base_url}}/admin/monitor/summary`

### GET `{{base_url}}/admin/monitor/activity?range=24h`

Supported ranges:

- `24h`
- `7d`

### GET `{{base_url}}/admin/monitor/health`

### GET `{{base_url}}/admin/monitor/top-books?limit=5`

### GET `{{base_url}}/admin/monitor/dashboard?range=7d&limit=5`

### GET `{{base_url}}/admin/settings`

Returns the current admin's profile summary.

### PATCH `{{base_url}}/admin/settings`

Change password for the current admin.

```json
{
  "current_password": "{{admin_password}}",
  "new_password": "AdminNewSecure456!",
  "new_password_confirmation": "AdminNewSecure456!"
}
```

Notes:

- After success, the current token is revoked.
- You must log in again.

Aliases:

- `PUT/PATCH/POST {{base_url}}/admin/settings/change-password`
- `PUT/PATCH/POST {{base_url}}/admin/settings/password`

### GET `{{base_url}}/admin/categories`

Admin category list with `books_count`.

### POST `{{base_url}}/admin/categories`

```json
{
  "name": "Mystery",
  "icon": "book-open"
}
```

Expected:

- `201 Created`
- response shape is the category object directly, not wrapped in `data`

### GET `{{base_url}}/admin/users`

Useful query params:

- `search=reader`
- `role=admin`
- `role=author`
- `role=user`
- `per_page=25`

### GET `{{base_url}}/admin/users/{{user_id}}`

### PATCH `{{base_url}}/admin/users/{{user_id}}`

```json
{
  "first_name": "Updated",
  "last_name": "User",
  "email": "updated.user@example.com",
  "role": "Author"
}
```

Accepted aliases:

- `firstname`, `firstName`
- `lastname`, `lastName`
- `role_name`
- `roleId`

### DELETE `{{base_url}}/admin/users/{{user_id}}`

Important:

- Admin cannot delete their own account.

### GET `{{base_url}}/admin/books`

Useful query params:

- `status=approved`
- `status=pending`
- `status=rejected`
- `search=history`

### GET `{{base_url}}/admin/books/approved`

### GET `{{base_url}}/admin/books/pending`

### POST `{{base_url}}/admin/books/{{book_id}}/approve`

Usually no body is required.

Expected:

- Book response object
- status changes to approved

### POST `{{base_url}}/admin/books/{{book_id}}/reject`

```json
{
  "rejection_reason": "Cover image is missing and PDF is unreadable."
}
```

Expected:

- Book response object
- status changes to rejected

### PATCH `{{base_url}}/admin/books/{{book_id}}/review`

Approve example:

```json
{
  "status": "approved"
}
```

Reject example:

```json
{
  "status": "rejected",
  "rejection_reason": "Please upload a valid PDF."
}
```

Important:

- This route only works for books currently in `pending` status.

## 9. Posts

These are public CRUD utility endpoints.

### GET `{{base_url}}/posts`

### POST `{{base_url}}/posts`

```json
{
  "title": "Postman Test Post",
  "content": "Created from Postman",
  "author": "Tester"
}
```

### GET `{{base_url}}/posts/1`

### PATCH `{{base_url}}/posts/1`

```json
{
  "title": "Updated title"
}
```

### DELETE `{{base_url}}/posts/1`

## Recommended End-to-End Test Order

Use this sequence for the smoothest workflow:

1. `GET /health`
2. `POST /auth/register` for a reader
3. `POST /auth/author_registration` for an author
4. `POST /auth/login`
5. `GET /user`
6. `GET /categories`
7. `GET /books`
8. `GET /books/{book_id}`
9. Reader tests: comment, rate, favorite, download
10. Reading session tests: start, heartbeat, finish, reading activity
11. Author login and upload a book
12. Admin login and review/approve the uploaded book
13. Re-test public book access on the approved book

## Useful Postman Scripts

### Save token from login

```javascript
const json = pm.response.json();
const token = json.data?.token;
if (token) {
    pm.environment.set("token", token);
}
```

### Save first book id from list

```javascript
const json = pm.response.json();
const bookId = json.data?.[0]?.id || json.books?.[0]?.id || json.results?.[0]?.id;
if (bookId) {
    pm.environment.set("book_id", bookId);
}
```

### Save first user id from admin list

```javascript
const json = pm.response.json();
const userId = json.data?.[0]?.id;
if (userId) {
    pm.environment.set("user_id", userId);
}
```

### Generic success assertion

```javascript
pm.test("Request succeeded", function () {
    pm.expect(pm.response.code).to.be.oneOf([200, 201]);
});
```

## Troubleshooting

### `401 Unauthorized`

Check:

- `Authorization: Bearer {{token}}` is present
- `token` was saved from the latest login
- the token was not revoked by logout or password change

### `403 Forbidden`

Usually means the account has the wrong role for that route.

### `404 Not Found`

Check:

- the request path includes `/api`
- `book_id`, `user_id`, or `category_id` exists
- readers are only trying to access approved books

### `409 Reading session is not active`

The session may already be finished or replaced by a newer active session.

### `422 Validation failed`

Most common causes:

- `content` missing for comments
- `rating` not between 1 and 5
- upload is missing `pdf`
- `status` missing on admin review
- rejection missing `rejection_reason`

## Notes

- Public book search endpoints can call Open Library. If network access is unavailable, local results can still succeed and `meta.external_error` may be populated.
- `POST /books/{book}/download` and `POST /books/{book}/downloads` are different routes. The first resolves a download URL, the second records an offline download.
- Some endpoints are legacy aliases kept for frontend compatibility. Prefer the clearer route where both exist, but test aliases if the client depends on them.
