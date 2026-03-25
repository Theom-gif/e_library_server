<div align="center">
<img width="1200" height="475" alt="GHBanner" src="https://github.com/user-attachments/assets/0aa67016-6eaf-458a-adb2-6e31a0763ed6" />
</div>

# Run and deploy your AI Studio app

This contains everything you need to run your app locally.

View your app in AI Studio: https://ai.studio/apps/4f30a24a-b846-4f79-b6a0-d4cac950bd6a

## Backend API contract (Home page + approval workflow)

The Home page (`frontend/src/pages/Home.tsx`) renders books from `LibraryProvider` (`frontend/src/context/LibraryContext.tsx`), which calls `bookService.list()` (`frontend/src/service/bookService.ts`).

### Base URL

Set your backend URL with `VITE_API_BASE_URL` (example):

```bash
VITE_API_BASE_URL="https://elibrary.pncproject.site"
```

The frontend expects JSON responses and will send `Authorization: Bearer <token>` automatically when `localStorage.token` exists.

### What Home needs

Home uses a single list of **approved** books and derives sections from it:

- “New Arrivals”: first 5 books returned
- “Recently Read / Trending / Top Rated”: slices of the same list

Minimum book fields required for the UI:

```json
{
  "id": 123,
  "title": "Atomic Habits",
  "author_name": "James Clear",
  "category_name": "Self-Help",
  "cover_image_url": "https://.../storage/covers/atomic-habits.jpg",
  "average_rating": 4.8
}
```

Notes:

- `cover_image_url` may also be a relative path like `storage/covers/x.jpg`; the frontend will normalize it using `VITE_API_BASE_URL`.
- Optional fields like `progress` / `timeLeft` are not required by the backend.

### Endpoints required (recommended)

#### Public (for users browsing Home)

- `GET /api/books`
  - Must return **approved** books only (recommended default sort: newest first)
  - Query params supported by frontend:
    - `q`, `category`, `page`, `per_page`, `sort=newest|rating|popular`
  - Response envelope (recommended):

```json
{
  "success": true,
  "message": "Books retrieved successfully.",
  "data": [/* array of approved books */],
  "meta": { "current_page": 1, "last_page": 10, "per_page": 50, "total": 500 }
}
```

Approval flags:

- Best: do server-side filtering so only approved books are returned
- If you include approval info, use one of:
  - `is_approved: 1` (or `true`), or
  - `status: "approved"` / `approval_status: "approved"`

The frontend will hide non-approved books **only when these fields exist**.

#### Author (upload book)

Authors upload books into a **pending** state. Use Bearer auth.

- `POST /api/author/books` (or `POST /api/books` with role-based access)
  - `multipart/form-data` fields (suggested):
    - `title` (string, required)
    - `category_id` (number, required)
    - `description` (string, optional)
    - `cover_image` (file image, optional)
    - `book_file` (file pdf/epub, required)
  - Response (suggested):

```json
{
  "message": "Book uploaded and pending approval.",
  "data": { "id": 123, "status": "pending" }
}
```

#### Admin (approve/reject)

Admins review pending books and approve them. Use Bearer auth.

- `GET /api/admin/books?status=pending`
  - List pending uploads for review
- `PATCH /api/admin/books/{id}/approve`
  - Marks the book as approved (sets `is_approved=1` / `status="approved"`)
- `PATCH /api/admin/books/{id}/reject`
  - Marks the book as rejected with optional reason

Once approved, the book **must appear in** `GET /api/books`.

### “Show after approval” behavior in the UI

The UI refreshes the book list on:

- app start
- tab focus (when returning to the page)
- periodic polling (about once per minute)

So after an admin approves a book, it will show on Home automatically without a hard refresh.

## Run Locally

**Prerequisites:**  Node.js


1. Install dependencies:
   `npm install`
2. Set the `GEMINI_API_KEY` in [.env.local](.env.local) to your Gemini API key
3. Run the app:
   `npm run dev`
