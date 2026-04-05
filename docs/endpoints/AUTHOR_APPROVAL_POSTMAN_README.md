# Author Approval Postman README

This guide covers the Postman requests for the author registration and admin approval flow.

## Base URLs

Use one base URL consistently for the whole flow.

- Local: `http://127.0.0.1:8000/api`
- Server: `https://elibrary.pncproject.site/api`

Do not mix tokens between local and server.

## Default Headers

Use these headers for JSON requests:

```http
Accept: application/json
Content-Type: application/json
```

For protected endpoints also add:

```http
Authorization: Bearer YOUR_TOKEN_HERE
```

## Testing Flow

1. Register an author
2. Login as admin
3. Check admin notifications
4. Approve or reject the author request

## 1. Register Author

Endpoint:

```http
POST /auth/author_registration
```

Full example:

```http
POST http://127.0.0.1:8000/api/auth/author_registration
```

Body:

```json
{
  "firstname": "Ktplay",
  "lastname": "Doe",
  "email": "ktplay_author@example.com",
  "password": "DYsmos@123456789",
  "password_confirmation": "DYsmos@123456789",
  "role_id": 2
}
```

Expected behavior:

- Creates user with `role_id = 2`
- Sets author status to `in_review`
- Sends email to the author
- Sends admin notification with type `author.pending_approval`
- Returns a token for the new author

## 2. Login As Admin

Endpoint:

```http
POST /auth/login
```

Full example:

```http
POST http://127.0.0.1:8000/api/auth/login
```

Body:

```json
{
  "email": "admin@example.com",
  "password": "your_admin_password"
}
```

Expected behavior:

- Returns an admin bearer token
- Use this token for all `/admin/*` endpoints

## 3. Check Admin Notifications

Endpoint:

```http
GET /admin/notifications
```

Full example:

```http
GET http://127.0.0.1:8000/api/admin/notifications
```

Headers:

```http
Accept: application/json
Authorization: Bearer ADMIN_TOKEN_HERE
```

Expected behavior:

- Admin sees notifications
- New author requests appear with:
  - `type = "author.pending_approval"`
  - `title = "New author request pending approval"`

Example response shape:

```json
{
  "success": true,
  "data": [
    {
      "id": 10,
      "user_id": 1,
      "role": "admin",
      "title": "New author request pending approval",
      "message": "Ktplay Doe requested to become an author.",
      "type": "author.pending_approval",
      "is_read": false,
      "data": {
        "author_id": 16,
        "email": "ktplay_author@example.com",
        "status": "in_review"
      }
    }
  ]
}
```

## 4. List Pending Authors

Endpoint:

```http
GET /admin/authors?status=pending
```

Full example:

```http
GET http://127.0.0.1:8000/api/admin/authors?status=pending
```

Headers:

```http
Accept: application/json
Authorization: Bearer ADMIN_TOKEN_HERE
```

Use this to get the author ID before approving or rejecting.

## 5. Approve Author

Endpoint:

```http
POST /admin/approve-authors/{author_id}
```

Full example:

```http
POST http://127.0.0.1:8000/api/admin/approve-authors/16
```

Headers:

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer ADMIN_TOKEN_HERE
```

Body:

```json
{}
```

Expected behavior:

- Author becomes active
- Status becomes `active`
- Approval email is sent to author

## 6. Reject Author

Endpoint:

```http
POST /admin/reject-authors/{author_id}
```

Full example:

```http
POST http://127.0.0.1:8000/api/admin/reject-authors/16
```

Headers:

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer ADMIN_TOKEN_HERE
```

Body:

```json
{}
```

Expected behavior:

- Rejection email is sent to author
- Author record is deleted from database
- Author tokens are deleted

## Common Errors

### `401 Unauthenticated.`

Cause:

- Token is missing
- Token is invalid
- Token was created on another server

Fix:

- Login again on the same base URL you are testing

### `403 You do not have permission to perform this action.`

Cause:

- You are using a non-admin token

Fix:

- Login with an admin account and use the admin token

## Postman Tips

- Save `base_url` as an environment variable
- Save admin token as `admin_token`
- Save author token as `author_token`

Example variables:

```text
base_url = http://127.0.0.1:8000/api
admin_token = YOUR_ADMIN_TOKEN
author_token = YOUR_AUTHOR_TOKEN
```

Then use:

```http
{{base_url}}/auth/author_registration
{{base_url}}/auth/login
{{base_url}}/admin/notifications
{{base_url}}/admin/authors?status=pending
{{base_url}}/admin/approve-authors/16
{{base_url}}/admin/reject-authors/16
```
