# Downloads UI → Backend endpoints

This document describes what the **Downloads** page needs from the backend.

The frontend stores offline files on the **device** using IndexedDB (no backend DB storage for downloaded bytes).

## Flow (what the UI does)

1) User clicks **Download** on a book.
2) Frontend calls a **download resolver** endpoint to get a `download_url`.
3) Frontend `fetch()`es the returned file URL and:
   - shows progress (needs `Content-Length`)
   - stores the bytes offline (IndexedDB)
4) User opens the book offline (Blob URL opened in a new tab).

## Required endpoints

### 1) List books (approved only)






















































































- `/api/books/{id}/download` returns `403` or `404` for non-approved books.- `/api/books` returns only approved books.Recommended behavior:## Approval rules (backend)  - `php artisan storage:link`- For Laravel storage: create the symlink and return `Storage::url($path)`:- Covers should be **public** (no Bearer token required), or use signed public URLs.Recommended:Cover image URLs are used directly in `<img src="...">`.## Covers (important for Downloads cards)- `Access-Control-Expose-Headers: Content-Length, Content-Disposition, Content-Type`If the file is cross-origin, expose headers:  - `inline; filename="Love never fails.pdf"`- `Content-Disposition`: recommended for filename- `Content-Length`: required for accurate progress percentage- `Content-Type`: `application/pdf` or `application/epub+zip`Headers required/recommended:The browser fetch must work (CORS if needed).`GET {download_url}`### 3) Serve the actual book file  - If you return S3/CloudFront/etc, return a **public or signed** URL that does not require the Bearer token.  - Best: return a **relative URL** like `/storage/books/123.pdf`.- The frontend only attaches the Bearer token to the **file fetch** if the `download_url` is **same-origin** as `VITE_API_BASE_URL`.Important:- If your API is protected, accept `Authorization: Bearer <token>`.Auth:```}  }    "size_bytes": 15623012    "file_name": "Love never fails.pdf",    "mime_type": "application/pdf",    "download_url": "/storage/books/123.pdf",  "data": {  "success": true,{```jsonRecommended response:- `url`- `stream_url`- `download_url` (recommended)The response must include one of:`POST /api/books/{id}/download`### 2) Resolve a downloadable file URL (used for download + read)```}  ]    }      "cover_image_url": "https://api.example.com/storage/covers/123.jpg"      "status": "approved",      "author_name": "Alex Rivera",      "title": "Love never fails",      "id": 123,    {  "data": [  "success": true,{```jsonExample:- `cover_image_url` (optional; if missing, UI shows **No cover**)- `author_name` (or `author`)- `title`- `id`Minimal fields used by the UI:Return only admin-approved books for reader users.n`GET /api/books`