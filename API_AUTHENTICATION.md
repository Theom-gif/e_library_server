# Authentication API Documentation

## Overview
Complete authentication system with login, registration, and password reset features.

---

## Base URL
```
http://localhost:8000/api
```

---

## Authentication
Protected endpoints require the authentication token in the header:
```
Authorization: Bearer {token}
```

---

## Endpoints

### 1. Register User
**POST** `/auth/register`

Register a new user account.

**Request Body:**
```json
{
  "firstname": "John",
  "lastname": "Doe",
  "email": "john@example.com",
  "password": "SecurePass123!",
  "password_confirmation": "SecurePass123!",
  "role_id": "1"
}
```

**Password Requirements:**
- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- At least one special character

**Success Response (201):**
```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "user": {
      "id": 1,
      "firstname": "John",
      "lastname": "Doe",
      "email": "john@example.com",
      "role_id": "1",
      "created_at": "2026-02-28T12:00:00Z",
      "updated_at": "2026-02-28T12:00:00Z"
    },
    "token": "auth_token_here..."
  }
}
```

**Error Response (422):**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["This email is already registered"]
  }
}
```

---

### 2. Login User
**POST** `/auth/login`

Authenticate user and get access token.

**Request Body:**
```json
{
  "email": "john@example.com",
  "password": "SecurePass123!"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "firstname": "John",
      "lastname": "Doe",
      "email": "john@example.com",
      "role_id": "1",
      "created_at": "2026-02-28T12:00:00Z",
      "updated_at": "2026-02-28T12:00:00Z"
    },
    "token": "auth_token_here..."
  }
}
```

**Error Response (401):**
```json
{
  "success": false,
  "message": "Invalid credentials",
  "errors": "Email or password is incorrect"
}
```

---

### 3. Request Password Reset
**POST** `/auth/request-password-reset`

Request a password reset token via email.

**Request Body:**
```json
{
  "email": "john@example.com"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Password reset link sent to your email",
  "data": null
}
```

**Error Response (422):**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["No user found with this email"]
  }
}
```

---

### 4. Reset Password
**POST** `/auth/reset-password`

Reset password using the token from the reset email.

**Request Body:**
```json
{
  "email": "john@example.com",
  "token": "reset_token_from_email",
  "password": "NewPassword123!",
  "password_confirmation": "NewPassword123!"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Password reset successfully",
  "data": null
}
```

**Error Responses:**

Token not found (400):
```json
{
  "success": false,
  "message": "Invalid token",
  "errors": "No reset request found for this email"
}
```

Token expired (400):
```json
{
  "success": false,
  "message": "Expired token",
  "errors": "The reset token has expired"
}
```

---

### 5. Get Current User ⭐ Protected
**GET** `/auth/me`

Get the current authenticated user's profile.

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "User retrieved successfully",
  "data": {
    "id": 1,
    "firstname": "John",
    "lastname": "Doe",
    "email": "john@example.com",
    "bio": "Software developer",
    "facebook_url": "https://facebook.com/john",
    "avatar": "avatars/user1.jpg",
    "role_id": "1",
    "created_at": "2026-02-28T12:00:00Z",
    "updated_at": "2026-02-28T12:00:00Z"
  }
}
```

---

### 6. Update Profile ⭐ Protected
**PATCH** `/auth/update-profile`

Update user profile information.

**Headers:**
```
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "firstname": "John",
  "lastname": "Smith",
  "bio": "Updated bio here",
  "facebook_url": "https://facebook.com/john",
  "avatar": "avatars/user1_new.jpg"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Profile updated successfully",
  "data": {
    "id": 1,
    "firstname": "John",
    "lastname": "Smith",
    "email": "john@example.com",
    "bio": "Updated bio here",
    "facebook_url": "https://facebook.com/john",
    "avatar": "avatars/user1_new.jpg",
    "role_id": "1",
    "updated_at": "2026-02-28T13:00:00Z"
  }
}
```

---

### 7. Change Password ⭐ Protected
**POST** `/auth/change-password`

Change the current user's password.

**Headers:**
```
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "current_password": "SecurePass123!",
  "new_password": "NewSecurePass456!",
  "new_password_confirmation": "NewSecurePass456!"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Password changed successfully",
  "data": null
}
```

**Error Response (401):**
```json
{
  "success": false,
  "message": "Invalid password",
  "errors": "Current password is incorrect"
}
```

---

### 8. Logout ⭐ Protected
**POST** `/auth/logout`

Logout and revoke the current authentication token.

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Logout successful",
  "data": null
}
```

---

## Testing with cURL

### Register
```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "firstname": "John",
    "lastname": "Doe",
    "email": "john@example.com",
    "password": "SecurePass123!",
    "password_confirmation": "SecurePass123!",
    "role_id": "1"
  }'
```

### Login
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "SecurePass123!"
  }'
```

### Get Current User (Protected)
```bash
curl -X GET http://localhost:8000/api/auth/me \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### Change Password (Protected)
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

---

## Error Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized |
| 422 | Validation Failed |
| 500 | Server Error |

---

## Features

✅ **User Registration** - Create new user accounts with role assignment  
✅ **User Login** - Authenticate and receive JWT token  
✅ **Password Reset** - Secure password reset with email token  
✅ **Profile Management** - Update user bio, avatar, and social profiles  
✅ **Password Change** - Change password while authenticated  
✅ **Token Security** - Sanctum API tokens for secure requests  
✅ **Validation** - Strong password policy and validation rules  
✅ **Error Handling** - Clean, consistent error responses  

---

## Notes

- All endpoints return consistent JSON responses with `success`, `message`, and `data` fields
- Authentication uses Laravel Sanctum tokens
- Passwords must contain uppercase, lowercase, numbers, and special characters
- Password reset tokens expire after 1 hour
- All dates are returned in ISO 8601 format
