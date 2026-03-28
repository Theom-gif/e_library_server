# Database Schema Guide

This document gives a high-level map of the database used by this Laravel app.

It covers:

- what each table is for
- how the tables relate to each other
- which tables are core application data and which are supporting framework tables

## Overview

The database is centered around these core entities:

- `roles`
- `users`
- `categories`
- `books`

Around those, the app stores activity, engagement, and notification data:

- reading sessions and progress
- favorites and comments
- ratings and view logs
- notifications
- achievements
- recommendation logs

There are also standard Laravel framework tables for auth, cache, sessions, and personal access tokens.

## Relationship Map

| Parent Table | Child Table | Relationship |
|---|---|---|
| `roles` | `users` | One role has many users |
| `users` | `books` | A user can upload or own many books through `books.user_id` |
| `users` | `books` | A user can approve many books through `books.approved_by` |
| `categories` | `books` | One category has many books |
| `books` | `book_views` | One book has many view logs |
| `books` | `book_comments` | One book has many comments |
| `book_comments` | `book_comment_likes` | One comment has many likes |
| `books` | `book_ratings` | One book has many ratings |
| `users` | `book_ratings` | One user can rate many books |
| `books` | `reading_sessions` | One book has many reading sessions |
| `users` | `reading_sessions` | One user has many reading sessions |
| `books` | `reading_progress` | One book has many progress rows |
| `users` | `reading_progress` | One user has many progress rows |
| `books` | `reading_histories` | One book has many reading history rows |
| `users` | `reading_histories` | One user has many reading history rows |
| `books` | `offline_downloads` | One book has many offline download records |
| `users` | `offline_downloads` | One user has many offline download records |
| `users` | `favorites` | One user has many favorite books |
| `books` | `favorites` | One book can appear in many users' favorites |
| `users` | `favorite_authors` | One user can follow many authors |
| `users` | `favorite_authors` | One author can be followed by many users |
| `users` | `user_notifications` | One user owns many notifications |
| `users` | `user_notifications` | One user can also be the creator of many notifications |
| `books` | `book_covers` | One book has one stored cover blob row |
| `users` | `user_avatars` | One user has one stored avatar blob row |
| `achievements` | `user_achievements` | One achievement can be unlocked by many users |
| `users` | `user_achievements` | One user can unlock many achievements |
| `users` | `user_reading_logs` | One user has many reading log rows |
| `books` | `user_reading_logs` | One book can appear in many reading log rows |
| `users` | `reading_activity_daily` | One user has one row per day |
| `users` | `recommendation_logs` | One user has many recommendation log rows |
| `books` | `recommendation_logs` | One book can appear in many recommendation log rows |

## Core Tables

### `roles`

Stores the role catalog used by `users.role_id`.

Main fields:

- `name`
- `description`

Relationship:

- one role can belong to many users

### `users`

Stores the application accounts.

Main fields:

- `role_id`
- `firstname`
- `lastname`
- `email`
- `password`
- profile fields added later in `add_profile_fields_to_users_table`

Relationships:

- belongs to one role
- has many books, favorites, sessions, ratings, comments, notifications, and activity rows

Note:

- the current `books` migration uses `user_id` and `approved_by`
- if you see legacy code referring to `author_id`, treat the migration schema as the source of truth

### `categories`

Stores book categories.

Main fields:

- `name`
- `slug`
- `description`
- `is_active`
- `icon`

Relationship:

- one category has many books

### `books`

Stores the main book catalog.

Main fields:

- `category_id`
- `user_id`
- `approved_by`
- `title`
- `slug`
- `author_name`
- `description`
- file and cover metadata
- `status`
- `approved_at`
- `published_at`
- `total_reads`
- `average_rating`

Relationships:

- belongs to a category
- belongs to a user through `user_id`
- belongs to an approver through `approved_by`
- has one stored cover row in `book_covers`
- has many views, comments, ratings, sessions, favorites, downloads, and activity rows

## Engagement Tables

### `book_views`

Stores raw view events for books.

Main fields:

- `book_id`
- `user_id` nullable
- `ip_address`
- `user_agent`
- `viewed_at`

Use:

- visit tracking
- admin traffic reports
- book popularity analytics

### `book_comments`

Stores comments on books.

Main fields:

- `book_id`
- `user_id`
- `parent_id` nullable for threaded replies
- `content`
- `likes_count`
- `is_edited`

Relationships:

- belongs to one book
- belongs to one user
- can belong to another comment as a reply

### `book_comment_likes`

Stores user likes on comments.

Main fields:

- `book_comment_id`
- `user_id`

Relationship:

- one comment can have many likes
- one user can like many comments

### `book_ratings`

Stores one rating per user per book.

Main fields:

- `book_id`
- `user_id`
- `rating`

Constraints:

- unique on `book_id + user_id`

### `favorites`

Stores books a user bookmarked or favorited.

Main fields:

- `user_id`
- `book_id`

Constraints:

- unique on `user_id + book_id`

### `favorite_authors`

Stores followed authors.

Main fields:

- `user_id`
- `author_id`

Constraints:

- unique on `user_id + author_id`

### `offline_downloads`

Stores offline download sync records.

Main fields:

- `book_id`
- `user_id`
- `local_identifier`
- `downloaded_at`
- `last_synced_at`
- `sync_status`

### `recommendation_logs`

Stores generated book recommendations for a user.

Main fields:

- `user_id`
- `book_id`
- `reason`
- `score`
- `generated_at`

Use:

- recommendation history
- debugging recommendation behavior

### `reading_status_posts`

Stores social reading posts such as started, progress, finished, quote, or custom updates.

Main fields:

- `user_id`
- `book_id` nullable
- `status_type`
- `content`
- `current_page`
- `is_public`
- `likes_count`

### `reading_status_comments`

Stores comments on reading status posts.

Main fields:

- `reading_status_post_id`
- `user_id`
- `content`

## Reading Tables

### `reading_sessions`

Stores individual reading sessions.

Main fields:

- `book_id`
- `user_id`
- `started_at`
- `ended_at`
- `duration_seconds`
- `start_page`
- `end_page`
- `status`
- `source`
- `last_heartbeat_at`
- `last_activity_at`
- `last_progress_percent`
- `heartbeat_count`
- `device_type`
- `is_offline`
- `synced_at`

Use:

- reader analytics
- author dashboards
- leaderboard calculations

### `reading_progress`

Stores the latest progress snapshot for a user and a book.

Main fields:

- `book_id`
- `user_id`
- `last_page`
- `progress_percent`
- `total_seconds`
- `total_sessions`
- `total_days`
- `last_read_at`
- `completed_at`

Constraints:

- unique on `book_id + user_id`

### `reading_histories`

Stores book start and finish timestamps for history tracking.

Main fields:

- `user_id`
- `book_id`
- `started_at`
- `finished_at`

Use:

- notification triggers
- reading history display
- achievement calculations

### `reading_activity_daily`

Stores daily aggregated reading activity per user.

Main fields:

- `user_id`
- `activity_date`
- `seconds_read`
- `minutes_read`
- `books_opened_count`

Constraints:

- unique on `user_id + activity_date`

### `user_reading_logs`

Stores daily reading log entries for a user and a book.

Main fields:

- `user_id`
- `book_id`
- `pages_read`
- `read_date`

Use:

- achievement checks
- reading behavior reporting

## Notification Tables

### `user_notifications`

Stores in-app notifications for readers, authors, and admins.

Main fields:

- `user_id`
- `created_by_user_id`
- `role`
- `type`
- `title`
- `message`
- `payload`
- `is_read`
- `read_at`

Relationships:

- belongs to one user as the notification owner
- belongs to one user as the notification creator

Use:

- user inbox
- author inbox
- admin inbox
- mark-as-read
- broadcast notifications

## Media Tables

### `user_avatars`

Stores one binary avatar blob per user.

Main fields:

- `user_id`
- `mime_type`
- `bytes`
- `hash`

Constraint:

- unique on `user_id`

### `book_covers`

Stores one binary cover blob per book.

Main fields:

- `book_id`
- `mime_type`
- `bytes`
- `hash`

Constraint:

- unique on `book_id`

## Achievement Tables

### `achievements`

Stores the achievement catalog.

Main fields:

- `code`
- `title`
- `description`
- `icon`
- `condition_type`
- `condition_value`

### `user_achievements`

Stores which achievements a user has unlocked.

Main fields:

- `user_id`
- `achievement_id`
- `unlocked_at`

Constraint:

- unique on `user_id + achievement_id`

## Framework Tables

### `personal_access_tokens`

Created by Laravel Sanctum.

Use:

- API token authentication

### `sessions`

Stores browser session data when session driver is database.

Use:

- web auth session state

### `password_reset_tokens`

Stores password reset records.

Use:

- password reset flow

### `cache`
### `cache_locks`

Stores cache entries and cache locks when the database cache driver is used.

Use:

- caching
- locking
- leaderboard or analytics caches

## Notes On Code Versus Schema

Some models and services still contain legacy references that may not exactly match the current migrations.

Examples:

- `Book` model has legacy helper logic that still references old asset and author conventions
- the current `books` table is defined by migrations, so the migration schema should be treated as the source of truth

## Short Summary

The database is organized around:

- users and roles
- books and categories
- reading activity and analytics
- notifications and social engagement
- stored media and achievements
- Laravel framework support tables

If you are debugging a feature, start by checking the relevant table in the matching category above.
