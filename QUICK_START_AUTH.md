# Quick Reference - Authentication API

## 🎯 All Available Endpoints

```
┌─────────────────────────────────────────────────────────────┐
│                PUBLIC ENDPOINTS (No Auth)                   │
├─────────────────────────────────────────────────────────────┤
│ POST   /api/auth/register                                   │
│ POST   /api/auth/login                                      │
│ POST   /api/auth/request-password-reset                     │
│ POST   /api/auth/reset-password                             │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│            PROTECTED ENDPOINTS (Auth Required)              │
├─────────────────────────────────────────────────────────────┤
│ GET    /api/auth/me                                         │
│ PATCH  /api/auth/update-profile                             │
│ POST   /api/auth/change-password                            │
│ POST   /api/auth/logout                                     │
└─────────────────────────────────────────────────────────────┘
```

---

## 📝 Request/Response Examples

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

### Update Profile (Protected)
```bash
curl -X PATCH http://localhost:8000/api/auth/update-profile \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "firstname": "John",
    "bio": "New bio",
    "facebook_url": "https://facebook.com/john"
  }'
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

### Logout (Protected)
```bash
curl -X POST http://localhost:8000/api/auth/logout \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### Request Password Reset
```bash
curl -X POST http://localhost:8000/api/auth/request-password-reset \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com"
  }'
```

### Reset Password
```bash
curl -X POST http://localhost:8000/api/auth/reset-password \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "token": "reset_token_from_email",
    "password": "NewPassword123!",
    "password_confirmation": "NewPassword123!"
  }'
```

---

## ✅ Key Features

- **Registration** - New user signup with role assignment
- **Login** - User authentication with token generation
- **Password Reset** - Secure token-based password reset
- **Profile Management** - Update user information
- **Password Change** - Change password while logged in
- **Logout** - Token revocation
- **Validation** - Strong password policy enforced
- **Security** - Hashed passwords, token-based auth, time-limited tokens
- **Clean Code** - Professional, well-documented code

---

## 🔑 Password Requirements

Minimum 8 characters with:
- ✅ At least one UPPERCASE letter
- ✅ At least one lowercase letter
- ✅ At least one number
- ✅ At least one special character (!@#$%^&*)

Example: `SecurePass123!`

---

## 📊 Response Format

### Success (2xx)
```json
{
  "success": true,
  "message": "Operation successful",
  "data": { /* response data */ }
}
```

### Error (4xx-5xx)
```json
{
  "success": false,
  "message": "Error description",
  "errors": { /* validation errors or error details */ }
}
```

---

## 🔐 Authentication Header

For protected endpoints, include:
```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

---

## 📂 Important Files

- **Controller:** `app/Http/Controllers/Api/AuthController.php`
- **Model:** `app/Models/User.php`
- **Routes:** `routes/api.php`
- **Validation:** `app/Http/Requests/*.php`
- **Documentation:** `API_AUTHENTICATION.md`

---

## 🚀 Getting Started

1. Ensure MySQL is running
2. Create database: `CREATE DATABASE e_library;`
3. Run migrations: `php artisan migrate`
4. Start testing endpoints!

---

## ❓ Troubleshooting

**"User not found"**
- Check email spelling
- Ensure user was registered first

**"Invalid credentials"**
- Verify email and password
- Remember password is case-sensitive

**"Token expired"**
- Re-login to get a new token
- For password reset, request a new reset token

**"Validation failed"**
- Check password meets requirements
- Ensure password_confirmation matches password
- Check all required fields are provided

---

For detailed documentation, see: [API_AUTHENTICATION.md](API_AUTHENTICATION.md)
