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

`POST /auth/author_registration`
Register an author (defaults to Author role if `role_id` is omitted or empty).
Same fields, validation, and aliases as `/auth/register`.

`POST /auth/login`
Login and receive an auth token.

Required fields:
`email`, `password`

Example:
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "SecurePass123!"
  }'
```

`POST /auth/request-password-reset`
Request a password reset token.

Required fields:
`email`

Note:
The token is stored hashed in `password_reset_tokens`. The email send is not implemented yet, so retrieve the token from the database during development.

Example:
```bash
curl -X POST http://localhost:8000/api/auth/request-password-reset \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com"
  }'
```

`POST /auth/reset-password`
Reset password using a valid reset token (expires after 1 hour).

Required fields:
`email`, `token`, `password`, `password_confirmation`

Example:
```bash
curl -X POST http://localhost:8000/api/auth/reset-password \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "token": "reset_token_from_email_or_db",
    "password": "NewSecurePass456!",
    "password_confirmation": "NewSecurePass456!"
  }'
```

---

**Protected Endpoints (Bearer Token Required)**

`GET /auth/me`
Returns the authenticated user.

Example:
```bash
curl -X GET http://localhost:8000/api/auth/me \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

`PATCH /auth/update-profile`
Update profile fields.

Optional fields:
`firstname`, `lastname`, `bio`, `facebook_url`, `avatar`

Example:
```bash
curl -X PATCH http://localhost:8000/api/auth/update-profile \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "firstname": "John",
    "bio": "Updated bio",
    "facebook_url": "https://facebook.com/john"
  }'
```

`POST /auth/change-password`
Change the current user's password.

Required fields:
`current_password`, `new_password`, `new_password_confirmation`

Accepted legacy field aliases:
`currentPassword`, `old_password`, `oldPassword`, `current`
`newPassword`, `password`, `new`, `new_pass`
`new_password_confirm`, `newPasswordConfirmation`, `confirmNewPassword`,
`confirm_new_password`, `confirm_password`, `confirmPassword`, `password_confirmation`

Example:
```bash
curl -X POST http://localhost:8000/api/auth/change-password \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "current_password": "SecurePass123!",
    "new_password": "NewSecurePass456!",
    "new_password_confirmation": "NewSecurePass456!"
  }'
```

`POST /auth/logout`
Revoke the current access token.

Example:
```bash
curl -X POST http://localhost:8000/api/auth/logout \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

---

**Implementation Pointers**

Controllers:
`app/Http/Controllers/Api/AuthController.php`

Requests:
`app/Http/Requests/RegisterRequest.php`
`app/Http/Requests/LoginRequest.php`
`app/Http/Requests/ChangePasswordRequest.php`
`app/Http/Requests/ResetPasswordRequest.php`
`app/Http/Requests/UpdateProfileRequest.php`
`app/Http/Requests/admin/RegistationAuthor.php`

Routes:
`routes/api.php`

