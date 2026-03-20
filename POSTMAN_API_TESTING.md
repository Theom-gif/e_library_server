# Postman API Testing Guide

This guide walks through testing the Laravel API in Postman, using the routes currently registered in `routes/api.php`.

## Base URL

Use one of these as your `base_url` environment variable in Postman:

- Local: `http://localhost:8000/api`
- Hosted: `https://elibrary.pncproject.site/api`

## Recommended Postman Environment

Create a Postman environment with these variables:

| Variable | Example Value | Notes |
| --- | --- | --- |
| `base_url` | `http://localhost:8000/api` | Main API base URL |
| `token` | leave empty | Filled after login |
| `book_id` | leave empty | Save from book responses |
| `user_id` | leave empty | Save from user/admin responses |
| `category_id` | leave empty | Save from category responses |

## Common Headers

For JSON requests:

```http
Content-Type: application/json
Accept: application/json
```

For protected routes, also add:

```http
Authorization: Bearer {{token}}
```

For file uploads, use Postman `form-data` and let Postman set the content type automatically.

## Suggested Collection Structure

Create folders in this order:

1. Health
2. Authentication
3. Categories
4. Public Books
5. Book Interactions
6. Favorites
7. Author
8. Admin
9. Posts

## 1. Health Check

### GET `{{base_url}}/health`

Expected:

- Status `200 OK`
- JSON with `status: healthy`

Tests:

```javascript
pm.test("Health is OK", function () {
    pm.response.to.have.status(200);
    const json = pm.response.json();
    pm.expect(json.status).to.eql("healthy");
});
```

## 2. Authentication

### POST `{{base_url}}/auth/register`

Body type: `raw` -> `JSON`

```json
{
  "firstname": "Test",
  "lastname": "User",
  "email": "testuser@example.com",
  "password": "SecurePass123!",
  "password_confirmation": "SecurePass123!",
  "role_id": 3
}
```

Expected:

- Status `200` or `201`
- Successful registration response

### POST `{{base_url}}/auth/author_registration`

Use this to create an author account.

```json
{
  "firstname": "Author",
  "lastname": "User",
  "email": "author@example.com",
  "password": "SecurePass123!",
  "password_confirmation": "SecurePass123!"
}
```

### POST `{{base_url}}/auth/login`

```json
{
  "email": "testuser@example.com",
  "password": "SecurePass123!"
}
```

Postman test script to save token:

```javascript
pm.test("Login successful", function () {
    pm.response.to.have.status(200);
});

const json = pm.response.json();
const token = json.token || json.data?.token || json.data?.access_token;

if (token) {
    pm.environment.set("token", token);
}
```

### GET `{{base_url}}/auth/me`

Headers:

- `Authorization: Bearer {{token}}`

Expected:

- Status `200`
- Current user payload

### PATCH `{{base_url}}/auth/update-profile`

```json
{
  "firstname": "Updated",
  "lastname": "User",
  "bio": "Updated from Postman"
}
```

### POST `{{base_url}}/auth/change-password`

```json
{
  "current_password": "SecurePass123!",
  "new_password": "NewSecurePass456!",
  "new_password_confirmation": "NewSecurePass456!"
}
```

### POST `{{base_url}}/auth/logout`

Expected:

- Status `200`
- Token revoked for current session

## 3. Categories

### GET `{{base_url}}/categories`

Optional aliases that also exist:

- `GET {{base_url}}/category`
- `GET {{base_url}}/categories/all`

### POST `{{base_url}}/categories`

If your app allows category creation without admin restriction on this route, test with:

```json
{
  "name": "Science Fiction"
}
```

After creation, save the returned ID:

```javascript
const json = pm.response.json();
const id = json.id || json.data?.id;
if (id) {
    pm.environment.set("category_id", id);
}
```

### GET `{{base_url}}/categories/{{category_id}}`

### PATCH `{{base_url}}/categories/{{category_id}}`

```json
{
  "name": "History"
}
```

### DELETE `{{base_url}}/categories/{{category_id}}`

## 4. Public Books

### GET `{{base_url}}/books`

Main public books listing route.

Compatibility alias:

- `GET {{base_url}}/book`

### GET `{{base_url}}/books/discover`

Use this to test discovery/search style public listing.

### GET `{{base_url}}/books/{{book_id}}`

Returns a single public book.

### GET `{{base_url}}/books/{{book_id}}/read`

Tests PDF/file reading route.

### GET `{{base_url}}/books/{{book_id}}/cover`

Tests cover image access route.

### POST `{{base_url}}/books/{{book_id}}/download`

Expected:

- A resolved download payload or file-access response, depending on backend behavior

## 5. Book Interactions

### GET `{{base_url}}/books/{{book_id}}/comments`

### GET `{{base_url}}/books/{{book_id}}/ratings`

Compatibility aliases:

- `GET {{base_url}}/books/{{book_id}}/rating`
- `GET {{base_url}}/ratings/{{book_id}}`

### POST `{{base_url}}/books/{{book_id}}/comments`

Protected route. Example JSON:

```json
{
  "comment": "Great book"
}
```

### POST `{{base_url}}/books/{{book_id}}/ratings`

Protected route. Example JSON:

```json
{
  "rating": 5
}
```

Compatibility aliases:

- `POST {{base_url}}/books/{{book_id}}/rating`
- `POST {{base_url}}/rating`

## 6. Favorites

All favorites routes require Bearer token.

### GET `{{base_url}}/favorites`

### POST `{{base_url}}/favorites`

Example JSON:

```json
{
  "book_id": {{book_id}}
}
```

### DELETE `{{base_url}}/favorites/{{book_id}}`

## 7. Author Routes

These routes require:

- Valid Bearer token
- User with `author` role

### GET `{{base_url}}/auth/books`

Returns author-managed books.

### GET `{{base_url}}/auth/books/{{book_id}}`

### POST `{{base_url}}/auth/book`

Body type: `form-data`

Suggested fields:

- `title` as text
- `author` as text
- `category` as text
- `description` as text
- `cover_image` as file
- `book_file` as file

### PATCH `{{base_url}}/auth/books/{{book_id}}`

Use either:

- `PATCH` directly, or
- `POST` with `_method=PATCH` if needed for compatibility

### DELETE `{{base_url}}/auth/books/{{book_id}}`

### GET `{{base_url}}/author/books`

Additional author workflow routes:

- `GET {{base_url}}/author/research`
- `GET {{base_url}}/author/search`
- `POST {{base_url}}/author/books/upload`
- `POST {{base_url}}/author/books`
- `GET {{base_url}}/author/books/search`
- `GET {{base_url}}/author/books/research`
- `GET {{base_url}}/author/books/{{book_id}}`
- `GET {{base_url}}/author/books/{{book_id}}/read`
- `GET {{base_url}}/author/books/{{book_id}}/cover`

Legacy upload aliases also exist:

- `POST {{base_url}}/author/upload`
- `POST {{base_url}}/author/books/upload`
- `POST {{base_url}}/author/books/create`

## 8. Admin Routes

These routes require:

- Valid Bearer token
- User with `admin` role

### GET `{{base_url}}/admin/dashboard`

### GET `{{base_url}}/admin/dashboard/activity`

### GET `{{base_url}}/admin/settings`

### PUT/PATCH/POST `{{base_url}}/admin/settings`

Compatibility aliases:

- `{{base_url}}/admin/settings/change-password`
- `{{base_url}}/admin/settings/password`

### GET `{{base_url}}/admin/categories`

### POST `{{base_url}}/admin/categories`

### GET `{{base_url}}/admin/users`

### GET `{{base_url}}/admin/users/{{user_id}}`

### PUT/PATCH/POST `{{base_url}}/admin/users/{{user_id}}`

### DELETE `{{base_url}}/admin/users/{{user_id}}`

### GET `{{base_url}}/admin/books`

### GET `{{base_url}}/admin/books/approved`

### GET `{{base_url}}/admin/books/pending`

### POST `{{base_url}}/admin/books/{{book_id}}/approve`

### POST `{{base_url}}/admin/books/{{book_id}}/reject`

### PATCH `{{base_url}}/admin/books/{{book_id}}/review`

## 9. Posts

These appear to be standard CRUD test routes.

### GET `{{base_url}}/posts`

### POST `{{base_url}}/posts`

```json
{
  "title": "Postman Test Post",
  "content": "Created from Postman",
  "author": "Tester"
}
```

Save the returned post ID if needed.

### GET `{{base_url}}/posts/1`

### PUT or PATCH `{{base_url}}/posts/1`

```json
{
  "title": "Updated Post Title"
}
```

### DELETE `{{base_url}}/posts/1`

## Useful Postman Tests

### Generic success test

```javascript
pm.test("Status code is success", function () {
    pm.expect(pm.response.code).to.be.oneOf([200, 201, 204]);
});
```

### Save a returned book ID

```javascript
const json = pm.response.json();
const bookId = json.id || json.data?.id || json.data?.[0]?.id;
if (bookId) {
    pm.environment.set("book_id", bookId);
}
```

### Save a returned user ID

```javascript
const json = pm.response.json();
const userId = json.id || json.data?.id || json.data?.[0]?.id;
if (userId) {
    pm.environment.set("user_id", userId);
}
```

## Recommended Test Flow

Run requests in this order for the smoothest setup:

1. `GET /health`
2. `POST /auth/register` or `POST /auth/author_registration`
3. `POST /auth/login`
4. `GET /auth/me`
5. `GET /categories`
6. `GET /books`
7. `GET /books/{book_id}`
8. Protected user features like comments, ratings, and favorites
9. Author routes if logged in as author
10. Admin routes if logged in as admin

## Common Troubleshooting

### `401 Unauthorized`

Check:

- `Authorization` header is present
- `token` variable is set
- Token belongs to the correct user

### `403 Forbidden`

Usually means the token is valid, but the user role is not allowed for that route.

### `404 Not Found`

Check:

- Correct `base_url`
- Correct path under `/api`
- The resource ID exists

### Validation errors

Make sure:

- JSON body keys match backend expectations
- File uploads use `form-data`
- `Accept: application/json` is included

## Final Notes

If you want, this can be turned into a ready-to-import Postman collection next, with folders, sample requests, and test scripts already filled in.
