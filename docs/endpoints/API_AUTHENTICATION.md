# Authentication API README

This document describes the authentication endpoints and request/response formats for this backend. All routes below are under the `/api` prefix (Laravel default).

**Base URL**: `http://localhost:8000/api`

**Auth mechanism**: Laravel Sanctum personal access tokens. Include `Authorization: Bearer <token>` for protected routes.

**Response shape**:

Success (2xx):
```json
{
  "success": true,
  "message": "Operation successful",
  "data": {}
}
```

Error (4xx-5xx):
```json
{
  "success": false,
  "message": "Error description",
  "errors": {}
}
```

**Password policy** (registration and password changes):
Minimum 8 characters with mixed case, numbers, and symbols.

---

**Public Endpoints**

`POST /auth/register`
Register a standard user.

Required fields:
`firstname`, `lastname`, `email`, `password`, `password_confirmation`, `role_id`

Notes:
`role_id` must exist in the `roles` table. If roles table is empty, it is auto-seeded with:
Admin=1, Author=2, User=3.

Accepted legacy field aliases:
`first_name`, `firstName`, `last_name`, `lastName`, `roleId`,
`password_confirm`, `confirm_password`, `confirmPassword`, `passwordConfirmation`

Example:
```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "firstname": "John",
    "lastname": "Doe",
    "email": "john@example.com",
    "password": "SecurePass123!",
    "password_confirmation": "SecurePass123!",
    "role_id": 3
  }'
```

... (document content copied)
