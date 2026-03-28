# Comment UI Backend Guide

This document explains the backend contract for the comment and review UI used by the frontend.

## Comment timestamps

The comment and review endpoints now return both:

- `created_at` / `updated_at` as ISO timestamps
- `created_at_human` / `updated_at_human` as relative strings like `7 hours ago`

Use the ISO value for sorting or live reformatting in the frontend, and the human value for immediate display.
