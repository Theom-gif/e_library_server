# Books API Testing Guide

This document is a practical guide for testing the book endpoints defined in `routes/api.php`.

All routes below use Laravel's `/api` prefix.

## Base URL

Use one of these values in Postman, Insomnia, or curl:

- Local: `http://localhost:8000/api`
- Hosted: `https://elibrary.pncproject.site/api`

## Common Headers

For protected endpoints, send:

```http
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

For multipart uploads, keep `Accept: application/json` and let the client set the boundary.

## Book Endpoints

### Public list of approved books

`GET /books`

Also available as:

`GET /book`

Query parameters supported by the backend:

- `per_page` - number of items per page
- `search`, `q`, `query`, `keyword`, `research` - search keyword
- `category_id` or `categoryId` - filter by category id
- `sort` or `sort_by` - `newest`, `oldest`, `title`, `title_asc`, `title_desc`, `rating`, `average_rating`, `reads`, `total_reads`, `created`, `updated`, `published`
- `order` - `asc` or `desc`

Example:

```bash
curl -X GET "https://elibrary.pncproject.site/api/books?per_page=10&search=magic&sort=newest" \
  -H "Accept: application/json"
```

Expected response shape:

```json
{
  "success": true,
  "message": "Approved books retrieved successfully.",
  "data": [],
  "books": [],
  "results": [],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 0
  }
}
```

Important:

- This endpoint returns only `approved` books.
- Each book includes fields like `id`, `title`, `slug`, `author_name`, `category_name`, `cover_image_url`, `pdf_url`, `read_url`, and `status`.

### Discover books

`GET /books/discover`

Purpose:

- Searches local approved books and optionally Open Library results.

Required query:

- One of `search`, `q`, `query`, `keyword`, or `research`

Optional query:

- `local_limit`
- `external_limit`
- `include_external` (`true` by default)
- `category_id`
- `sort`
- `order`

Example:

```bash
curl -X GET "https://elibrary.pncproject.site/api/books/discover?q=space&include_external=true" \
  -H "Accept: application/json"
```

### View a single book

`GET /books/{book}`

This uses Laravel route model binding with the book id.

Example:

```bash
curl -X GET "https://elibrary.pncproject.site/api/books/1" \
  -H "Accept: application/json"
```

### Stream the PDF

`GET /books/{book}/read`

Returns the PDF file as a file response when the book is approved or accessible to the current user.

Example:

```bash
curl -L "https://elibrary.pncproject.site/api/books/1/read" -o book.pdf
```

### View the cover

`GET /books/{book}/cover`

If the cover is stored locally, this returns the image file. If the cover is a public URL, it may return JSON with `cover_url`.

Example:

```bash
curl -L "https://elibrary.pncproject.site/api/books/1/cover" -o cover.jpg
```

### Resolve download link

`POST /books/{book}/download`

This endpoint resolves the final download or stream URL for an approved book.

Example:

```bash
curl -X POST "https://elibrary.pncproject.site/api/books/1/download" \
  -H "Accept: application/json"
```

Expected response shape:

```json
{
  "success": true,
  "message": "Download link generated successfully.",
  "download_url": "/api/books/1/read",
  "stream_url": "/api/books/1/read",
  "url": "/api/books/1/read",
  "data": {
    "download_url": "/api/books/1/read",
    "stream_url": "/api/books/1/read",
    "url": "/api/books/1/read",
    "mime_type": "application/pdf",
    "file_name": "book.pdf",
    "size_bytes": 123456
  }
}
```

## Comments, Reviews, and Ratings

### List comments

`GET /books/{book}/comments`

### List reviews

`GET /books/{book}/reviews`

### List ratings summary

`GET /books/{book}/ratings`

### Create comment

`POST /books/{book}/comments`

Auth required.

### Create review

`POST /books/{book}/reviews`

Auth required.

### Rate a book

`POST /books/{book}/ratings`

or legacy alias:

`POST /books/{book}/rating`

Auth required.

## Author Endpoints

All author endpoints below require `auth:sanctum` and `role:author` or `role:admin` where applicable.

### My books

`GET /auth/books`

If the frontend needs reader counts for analytics, prefer the backend contract in
[`reader-counts-per-book-backend.md`](./reader-counts-per-book-backend.md).

Example:

```bash
curl -X GET "https://elibrary.pncproject.site/api/auth/books?status=approved" \
  -H "Authorization: Bearer <token>" \
  -H "Accept: application/json"
```

### Upload a book

`POST /auth/book`

Also available as:

- `POST /author/books`
- `POST /author/books/upload`
- `POST /author/books/create`
- `POST /books`

Recommended form-data fields:

- `title` - required
- `category` or `category_id` - required
- `description` - optional
- `author_name` - optional
- `language` - optional
- `total_pages` - optional
- `pdf` - required file
- `cover_image` - optional image file

Example:

```bash
curl -X POST "https://elibrary.pncproject.site/api/auth/book" \
  -H "Authorization: Bearer <token>" \
  -H "Accept: application/json" \
  -F "title=Sample Book" \
  -F "category=Fantasy" \
  -F "description=Example description" \
  -F "pdf=@C:/path/to/book.pdf" \
  -F "cover_image=@C:/path/to/cover.jpg"
```

Expected response:

```json
{
  "success": true,
  "message": "Book uploaded and submitted for admin approval.",
  "data": {}
}
```

### Update a book

`PATCH /auth/books/{book}`

Also accepts `POST` and `PUT` in some flows through the controller.

### Delete a book

`DELETE /auth/books/{book}`

## Admin Endpoints

All admin endpoints require `auth:sanctum` and `role:admin`.

### List books

`GET /admin/books`

Optional query:

- `status` - `approved`, `pending`, `rejected`, or `All`
- `search`

### Approved books

`GET /admin/books/approved`

### Pending books

`GET /admin/books/pending`

### Approve a book

`POST /admin/books/{book}/approve`

### Reject a book

`POST /admin/books/{book}/reject`

## Common Status Codes

- `200` - request succeeded
- `201` - resource created
- `204` - successful delete with no body
- `404` - book not found or not accessible
- `422` - validation failed
- `500` - server-side exception

## Quick Debug Checklist

If `GET /api/books` returns `500`:

1. Check `storage/logs/laravel.log`.
2. Confirm the database has approved books.
3. Confirm cover and PDF fields do not contain broken legacy file paths.
4. Confirm the frontend is calling `/api/books`, not an admin-only endpoint.
