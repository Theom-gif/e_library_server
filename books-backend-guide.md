# Admin Books Table API (contract for `Books.jsx`)

This guide defines the endpoints and payload shape needed to power the Admin “Books” table (`src/admin/pages/Books.jsx`). It replaces the current mock `BOOKS` array with live data once implemented on the backend.

## Primary endpoint

```
GET /api/admin/books
Authorization: Bearer <token>
Query params (optional):
  status   Approved | Pending | Rejected | All   // defaults to All
  search   string                             // free text across title/author/category
  category string                             // exact match; omit or use All for no filter
  sort     downloads_desc | downloads_asc | date_desc | date_asc (optional)
  page     number (optional, if you paginate)
  per_page number (optional, if you paginate)
```

**Response body**

```json
{
  "data": [
    {
      "id": 7,
      "title": "Love never fails",
      "author": "Alex Rivera",
      "category": "Romance",
      "status": "Pending",
      "downloads": 0,
      "cover_image_path": "covers/love-never-fails.jpg",
      "date": "2026-03-16"
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 1
  }
}
```

### Field notes (what the UI reads)
- `id`            number|string — unique key shown as “ID #x”.
- `title`         string — primary text.
- `author`        string — secondary text.
- `category`      string — shown as a chip.
- `status`        "Approved" | "Pending" | "Rejected" — drives badge color and table filter.
- `downloads`     number — displayed with thousand separators.
- `cover_image_path` or `cover_image_url` — relative paths will be prefixed with `${VITE_API_BASE_URL}/storage/`; absolute URLs are used as-is.
- `date`          string — shown in the small uppercase date label (any ISO or short date string is acceptable).

## Optional supporting endpoint (counts)

```
GET /api/admin/books/counts
Authorization: Bearer <token>
Response:
{ "approved": 120, "pending": 12, "rejected": 4, "total": 136 }
```

Useful for dashboards or quick badges; the Books table itself does not require it.

## Pending-only shortcut (used elsewhere)
If you expose `GET /api/admin/books/pending`, keep the same shape as above; it can be a convenience alias for `status=Pending`.

## HTTP semantics
- 200 on successful GET.
- 400 on bad query params (return `{ message, errors? }`).
- 401/403 on auth failures.
- 5xx on server errors.

## Testing checklist
- [ ] `GET /api/admin/books` returns `data` as an array with the fields above.
- [ ] Status filtering works for Approved/Pending/Rejected/All.
- [ ] `search` matches title/author/category case-insensitively.
- [ ] Downloads and date fields are numbers/strings respectively; no nulls required.
- [ ] Relative cover paths render correctly when prefixed with `/storage/`.
- [ ] If pagination is implemented, include `meta.page/per_page/total`; otherwise you may omit `meta`.

Keeping this contract stable will let the Books page switch from mock data to live API responses without further UI changes.***
