Backend API contract for books used by the admin and author UIs. Base URL defaults to `https://elibrary.pncproject.site` but can be overridden with `VITE_API_BASE_URL`. All requests include `Authorization: Bearer <token>` when a token is available.

Data fields expected on book objects:
- `id` number
- `title` string
- `author` string (or `authorName`)
- `category` string (shown as Genre)
- `status` string: Approved | Pending | Rejected
- `downloads` number
- `cover_image_url` or `cover_image_path` (joined as `${API_BASE_URL}/storage/${cover_image_path}`)
- `book_file_url` or `book_file_path` (joined as `${API_BASE_URL}/storage/${book_file_path}`)
- `description` string
- `first_publish_year` number (optional)
- `manuscript_type` string MIME (optional)
- `manuscript_size_bytes` number (optional)
- `date` string label shown in admin table

List books (admin dashboard table):
- Method: GET `/admin/books`
- Query params: `status` in Approved|Pending|Rejected|All, `search` free text across title|author|category
- Response: `{ data: Book[] }` where each Book includes fields above plus `downloads`

Approve or reject a book:
- Method: POST `/admin/books/{id}/approve`
- Method: POST `/admin/books/{id}/reject`
- Response: updated Book

List author’s own books (My Books):
- Method: GET `/api/auth/books`
- Response: `{ data: Book[] }` with file URLs/paths so the detail page can display cover and manuscript

Get single book (author detail view):
- Method: GET `/api/auth/books/{id}`
- Response: Book with `description`, `genre/category`, `manuscript` URLs, and optional `manuscript_type`, `manuscript_size_bytes`

Create a book (author upload):
- Method: POST `/api/auth/book`
- Content-Type: `multipart/form-data`
- Body fields: `title`, `author`, `category`, `description`, optional `cover_image` file, required `book_file` file (PDF preferred), optional `genre`, optional `first_publish_year`
- Response: created Book

Update a book:
- Method: PATCH `/api/auth/books/{id}` (accept `POST` + `_method=PATCH` for compatibility with current frontend)
- Content-Type: `multipart/form-data`
- Body fields: same as create; files are optional but, if provided, replace previous files
- Response: updated Book

Delete a book:
- Method: DELETE `/api/auth/books/{id}`
- Response: 204 No Content or `{ success: true }`

File access expectations:
- Frontend builds file URLs as `${API_BASE_URL}/storage/{relativePath}`; ensure storage is publicly readable or returns the file with proper CORS headers.
- Direct absolute URLs (already hosted) should be returned untouched to allow external assets.

Examples:
```
GET /admin/books?status=Pending&search=history
200 OK
{
  "data": [
    {
      "id": 42,
      "title": "World History",
      "author": "Jane Smith",
      "category": "History",
      "status": "Pending",
      "downloads": 1280,
      "cover_image_path": "covers/world-history.jpg",
      "date": "Feb 2026"
    }
  ]
}
```

```
POST /api/auth/book
Content-Type: multipart/form-data
Fields: title, author, category, description, cover_image (file), book_file (file)
```
