# Reader Counts Per Book - Backend README

## Purpose
This document defines the backend data needed for the Analytics UI to show reader counts per book and compute percentage share in a pie chart.

## What the frontend needs
For each book, the frontend needs a total reader count:

`totalReaders`

The frontend will compute:

`percent = (book.totalReaders / sumAllReaders) * 100`

## Accepted Backend Options (Choose One)

### Option A - Per-Book Analytics Endpoint (recommended)
Provide a per-book endpoint used by the frontend:

`GET /api/books/{id}/analytics`

`Authorization: Bearer <token>`

Response:

```json
{
  "id": 12,
  "totalReaders": 245,
  "completionRate": 37.5,
  "monthlyReads": 89
}
```

Notes:

- `totalReaders` is required.
- `completionRate` and `monthlyReads` are optional.

### Option B - Include Reader Counts in Book List
Return `totalReaders` inside the author books list:

`GET /api/auth/books`

`Authorization: Bearer <token>`

Response:

```json
{
  "data": [
    {
      "id": 12,
      "title": "Book Name",
      "author": "Author Name",
      "totalReaders": 245
    }
  ]
}
```

If you choose this option, the frontend can skip extra analytics calls.

## Data Rules

- `totalReaders` must be a number (`0` allowed).
- If there are no readers yet, return `0` not `null`.
- Use only readers who have actually read the book. Define that rule clearly on the backend.

## Example Calculation (Frontend)

If the backend returns:

```json
[
  { "id": 1, "totalReaders": 100 },
  { "id": 2, "totalReaders": 50 }
]
```

Frontend will show:

- Book 1 = 66.7%
- Book 2 = 33.3%

## Validation Checklist

- [ ] `totalReaders` exists for every book
- [ ] `totalReaders` is a number
- [ ] Books with no readers return `0`
- [ ] Endpoint works for the authenticated author only

