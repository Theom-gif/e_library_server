# Quick Start Guide - Laravel REST API

## Start Development Server

```bash
cd /path/to/VC1-Backend
php artisan serve
```

Server will run at: **http://localhost:8000**

## Start Redis

If you want the local Redis server for cache, queues, or sessions, run:

```bash
docker compose up -d redis
```

Redis will be available to Laravel at `127.0.0.1:6379`, matching the default `.env.example` values.

## Connect RedisInsight Desktop

In RedisInsight Desktop, click **Connect existing database** and use:

- Database Alias: `VC1 Redis`
- Host: `localhost`
- Port: `6379`
- Username: leave empty
- Password: leave empty
- TLS: off

If you prefer the browser-based RedisInsight container instead, run:

```bash
docker compose up -d redis redisinsight
```

Then open: **http://localhost:5540**

## Redis Keys From User Activity

The backend records common high-traffic interactions in Redis:

- Successful login writes keys like `metrics:logins:total`, `metrics:logins:daily:YYYY-MM-DD`, and `users:{id}:last_login_at`.
- Opening a book detail page writes keys like `metrics:books:detail_view:total`, `metrics:books:{bookId}:detail_view`, and `metrics:books:detail_view:top`.
- Opening a book PDF writes keys like `metrics:books:read:total`, `metrics:books:{bookId}:read`, and `metrics:books:read:top`.

Use RedisInsight Desktop to refresh the `db0` browser after logging in or opening a book.

## Test the API

### Health Check
```bash
curl http://localhost:8000/api/health
```

### Create a Post
```bash
curl -X POST http://localhost:8000/api/posts \
  -H "Content-Type: application/json" \
  -d '{
    "title": "My First Post",
    "content": "This is the content of my post",
    "author": "John Doe"
  }'
```

### Get All Posts
```bash
curl http://localhost:8000/api/posts
```

### Get Specific Post
```bash
curl http://localhost:8000/api/posts/1
```

### Update a Post
```bash
curl -X PUT http://localhost:8000/api/posts/1 \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Updated Title",
    "content": "Updated content"
  }'
```

### Delete a Post
```bash
curl -X DELETE http://localhost:8000/api/posts/1
```

## Using Postman or Insomnia

1. Copy the curl commands above into Postman/Insomnia
2. Change request method to POST/PUT/DELETE as needed
3. Set Content-Type header to application/json
4. Paste JSON data in the body (raw format)

## Database

The project uses **SQLite** by default. The database file is located at:
```
database/database.sqlite
```

To view/manage the database, use:
- SQLite CLI: `sqlite3 database/database.sqlite`
- GUI Tools: DB Browser for SQLite, DBeaver

## Key Files to Modify

1. **Routes** - `routes/api.php` - Add new endpoints
2. **Controllers** - `app/Http/Controllers/Api/` - Business logic
3. **Models** - `app/Models/` - Database interaction
4. **Migrations** - `database/migrations/` - Database schema
5. **Config** - `.env` - Environment variables

## Useful Commands

```bash
# List all routes
php artisan route:list

# Run migrations
php artisan migrate

# Rollback migrations
php artisan migrate:rollback

# Fresh database (rollback + migrate)
php artisan migrate:fresh

# Run tests
php artisan test

# Create new model with migration
php artisan make:model ModelName -m

# Create new API controller
php artisan make:controller Api/ResourceController --api
```

## Troubleshooting

**Port 8000 already in use?**
```bash
php artisan serve --port=8001
```

**Code changes not reflecting?**
```bash
php artisan cache:clear
php artisan config:clear
```

**Permission errors?**
```bash
chmod -R 777 storage bootstrap/cache
```

---

For detailed information, see [LARAVEL_SETUP.md](./LARAVEL_SETUP.md)
