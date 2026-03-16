# Admin Categories API (contract for `Categories.jsx`)

This guide translates the UI in `src/admin/pages/Categories.jsx` into the backend endpoints it expects. It mirrors the style of `dashboard-backend-guide.md` and keeps fields stable so the mock data in `CATEGORIES` can be replaced by live responses without UI changes.

## Primary endpoints

```
GET /api/admin/categories
POST /api/admin/categories
```

### 1) List categories

```
GET /api/admin/categories
Authorization: Bearer <token>
Query (optional):
  search   string   // filters by name (case-insensitive)
```

**Response body**

```json
{
  "data": [
    { "id": 1, "name": "Technology", "count": 128, "icon": "Tech" },
    { "id": 2, "name": "Novel", "count": 156, "icon": "Bookopen" },
    { "id": 3, "name": "Education", "count": 89, "icon": "GraduationCap" }
  ]
}
```

### 2) Create category

```
POST /api/admin/categories
Authorization: Bearer <token>
Content-Type: application/json
Body:
{
  "name": "History",
  "icon": "Landmark"   // optional; backend may default
}
```

**Response body**

```json
{
  "id": 6,
  "name": "History",
  "count": 0,
  "icon": "Landmark"
}
```

## Field notes (what the UI reads)
- `id`           number | string — unique key used as React `key`.
- `name`         string — displayed title.
- `count`        number — drives “Total Books”, the grid badges, and the “Average Books” calculation (frontend sums them).
- `icon`         string — optional; maps to Lucide icons. Supported keys today: `Tech`, `Bookopen`, `GraduationCap`, `Briefcase`, `Landmark`. Unknown keys fall back to a generic icon on the frontend.

## UI expectations & behavior
- The category grid and metrics are fully client-driven from the `GET /api/admin/categories` payload.
- When an admin creates a category, the UI appends the returned object to the list. No additional fields are required beyond the contract above.
- Counts can be zero; the UI handles that gracefully.

## HTTP semantics
- 200 on successful GET with the JSON shape above.
- 201 on successful POST returning the created category object.
- 400 with `{ message, errors? }` on validation issues (e.g., missing name or duplicate).
- 401/403 on auth failures.
- 5xx on server errors.

## Testing checklist for backend
- [ ] `GET /api/admin/categories` returns an array under `data`.
- [ ] Each category object includes `id`, `name`, `count`; `icon` is optional but allowed.
- [ ] `POST /api/admin/categories` validates `name` and returns the created object with `count` initialized (0 recommended).
- [ ] Optional `search` query filters by name without breaking pagination (if added later).
- [ ] Endpoint responds within `VITE_API_TIMEOUT_MS` (default 8000 ms).

Keeping this contract stable will let the Categories page swap mock data for live API responses with minimal frontend changes.
