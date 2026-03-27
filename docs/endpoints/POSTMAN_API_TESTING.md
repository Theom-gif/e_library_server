# API Testing Guide

This document is a practical Postman guide for testing the current backend API defined in `routes/api.php`.

All endpoints below assume Laravel's `/api` prefix.

## Base URL

Use one of these values for a Postman environment variable named `base_url`:

- Local: `http://localhost:8000/api`
- Hosted: `https://elibrary.pncproject.site/api`

## Recommended Postman Environment

Create these variables before testing:

| Variable | Example | Purpose |
| --- | --- | --- |
| `base_url` | `http://localhost:8000/api` | API root |
| `token` | empty | Bearer token from login |
| `reader_email` | `reader@example.com` | Reader login |
| `reader_password` | `SecurePass123!` | Reader password |
| `author_email` | `author@example.com` | Author login |
| `author_password` | `SecurePass123!` | Author password |
| `admin_email` | `admin@example.com` | Admin login |
| `admin_password` | `SecurePass123!` | Admin password |
| `book_id` | empty | Saved from book responses |
| `category_id` | empty | Saved from category responses |
| `user_id` | empty | Saved from admin user responses |
| `session_id` | empty | Saved from reading session start |

... (document content copied)
