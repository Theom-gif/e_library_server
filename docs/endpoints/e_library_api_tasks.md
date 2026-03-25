# 📚 E-Library API Tasks

This document outlines the API tasks and endpoints for the **E-Library system**, including specifications for book retrieval and user management.

---

## 1️⃣ Endpoint: `/users/books`

**Description:**
Retrieve all books that have been **approved**.

**Method:** `GET`
**Route:** `/users/books`

**Request Parameters:**
- None

**Response Example:**
```json
[
  {
    "id": 1,
    "title": "Learning Laravel",
    "authorId": 2,
    "status": "approved",
    "created_at": "2026-03-18T07:00:00Z",
    "updated_at": "2026-03-18T07:00:00Z"
  },
  {
    "id": 2,
    "title": "React Basics",
    "authorId": 3,
    "status": "approved",
    "created_at": "2026-03-18T08:00:00Z",
    "updated_at": "2026-03-18T08:00:00Z"
  }
]
```

**Notes:**
- Only books with `status = "approved"` should be returned.
- Response should be paginated if the number of books is large.

---

## 2️⃣ Endpoint: `/authors/books`

**Description:**
Retrieve all books associated with a specific author.

**Method:** `GET`
**Route:** `/authors/books`

**Request Parameters:**
- `authorId` (query or path parameter) – the ID of the author

**Response Example:**
```json
[
  {
    "id": 3,
    "title": "Advanced PHP",
    "authorId": 2,
    "status": "approved",
    "created_at": "2026-03-17T10:00:00Z",
    "updated_at": "2026-03-17T10:00:00Z"
  },
  {
    "id": 4,
    "title": "Laravel Tips",
    "authorId": 2,
    "status": "pending",
    "created_at": "2026-03-17T12:00:00Z",
    "updated_at": "2026-03-17T12:00:00Z"
  }
]
```

**Notes:**
- Return all books where `book.userId = author.id`.
- Include all book statuses unless otherwise specified.

---

## 3️⃣ Endpoint: `/baned/account`

**Description:**
Manage banned users. Prevent banned users from logging in.

**Steps:**

1. **Create `banned_users` Table**

**Columns:**
| Column      | Type          | Notes                        |
|------------|---------------|-------------------------------|
| id         | int, PK       | Auto-increment               |
| userId     | int           | References `users.id`        |
| reason     | varchar(255)  | Optional ban reason          |
| created_at | timestamp     | Record creation time         |
| updated_at | timestamp     | Record last update time      |

2. **Login Check**
- During login, check if the `userId` exists in the `banned_users` table.
- If yes, **deny login** and return a message:
```json
{
  "status": "error",
  "message": "Your account is banned. Please contact support."
}
```

**Notes:**
- Ensure `userId` in `banned_users` has a foreign key constraint with `users.id`.
- Optionally include an admin interface to manage bans.

---

## ✅ Best Practices

- Use **RESTful API standards**.
- Return **HTTP status codes**:  
  - `200 OK` → Successful GET  
  - `403 Forbidden` → Banned user attempting login  
  - `404 Not Found` → No books found
- Include **pagination** for large lists.
- Include **proper validation** for parameters.

---

**End of Document**
