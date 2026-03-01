# Laravel REST API Development Guide

## Project Overview

This is a **Laravel** REST API backend project for VC1. The project is configured with:

- **Laravel 12** - Latest framework version
- **SQLite** - Development database (configured in `.env`)
- **PHP** - Server-side scripting language
- **Composer** - Dependency management
- **Artisan CLI** - Laravel command interface

## Project Structure

```
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/           # API controllers
│   │   │       └── PostController.php
│   │   └── Middleware/
│   ├── Models/                # Eloquent models
│   │   └── Post.php
│   └── ...
├── database/
│   ├── migrations/            # Database migrations
│   │   └── 2026_02_28_081721_create_posts_table.php
│   └── seeders/
├── routes/
│   ├── api.php               # API routes
│   ├── web.php               # Web routes
│   └── console.php
├── config/                   # Configuration files
├── resources/
├── storage/                  # Logs, cache, and uploads
├── tests/                    # Unit and feature tests
├── .env                      # Environment configuration (local)
├── .env.example              # Environment template
├── artisan                   # Laravel CLI
├── composer.json             # Dependencies
└── composer.lock             # Locked dependency versions
```

## Getting Started

### Prerequisites

- PHP 8.2 or higher
- Composer
- SQLite (or other database of choice)

### Installation Steps

1. **Install dependencies** (if not already installed):
   ```bash
   composer install
   ```

2. **Generate application key**:
   ```bash
   php artisan key:generate
   ```

3. **Run migrations**:
   ```bash
   php artisan migrate
   ```

4. **Start development server**:
   ```bash
   php artisan serve
   ```
   The API will be available at `http://localhost:8000`

## Available API Endpoints

### Health Check
- **GET** `/api/health` - Check API status
  ```bash
  curl http://localhost:8000/api/health
  ```
  Response:
  ```json
  {
    "status": "healthy",
    "timestamp": "2026-02-28T08:17:21.000000Z"
  }
  ```

### Posts Resource (Example REST API)
- **GET** `/api/posts` - List all posts
- **POST** `/api/posts` - Create a new post
- **GET** `/api/posts/{id}` - Get a specific post
- **PUT** `/api/posts/{id}` - Update a post
- **DELETE** `/api/posts/{id}` - Delete a post

Example POST request:
```bash
curl -X POST http://localhost:8000/api/posts \
  -H "Content-Type: application/json" \
  -d '{
    "title": "My First Post",
    "content": "This is the content",
    "author": "John Doe"
  }'
```

## Development Workflow

### Create a New Resource

1. **Generate Model with Migration**:
   ```bash
   php artisan make:model ResourceName -m
   ```

2. **Update Migration** (in `database/migrations/`):
   ```php
   $table->string('field_name');
   $table->text('description');
   ```

3. **Create API Controller**:
   ```bash
   php artisan make:controller Api/ResourceNameController --api
   ```

4. **Add Route** (in `routes/api.php`):
   ```php
   Route::apiResource('resources', \App\Http\Controllers\Api\ResourceNameController::class);
   ```

5. **Run Migration**:
   ```bash
   php artisan migrate
   ```

### Useful Artisan Commands

```bash
# Create model with migration and controller
php artisan make:model Post -m -c --api

# Create migration
php artisan make:migration create_table_name --create=table_name

# Create request class (for validation)
php artisan make:request StorePostRequest

# List all routes
php artisan route:list

# Clear cache
php artisan cache:clear
php artisan config:clear

# Database operations
php artisan migrate              # Run migrations
php artisan migrate:rollback     # Rollback to previous
php artisan migrate:refresh      # Rollback and re-run
php artisan db:seed              # Run seeders
```

## Configuration

### Environment Variables (`.env`)

Key configuration variables:

```env
APP_NAME="VC1 API"           # Application name
APP_DEBUG=true               # Enable debug mode (set to false in production)
APP_URL=http://localhost:8000 # Application URL

DB_CONNECTION=sqlite         # Database type
DB_DATABASE=database.sqlite  # SQLite file path

LOG_CHANNEL=stack            # Logging channel
LOG_LEVEL=debug              # Log level
```

### Database Configuration

To switch from SQLite to MySQL:

1. Update `.env`:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=vc1_api
   DB_USERNAME=root
   DB_PASSWORD=
   ```

2. Ensure MySQL is running and database exists:
   ```bash
   mysql -u root -e "CREATE DATABASE vc1_api;"
   ```

## API Response Format

### Success Response
```json
{
  "data": {
    "id": 1,
    "title": "Post Title",
    "content": "Post content",
    "author": "Author Name",
    "created_at": "2026-02-28T08:17:21.000000Z",
    "updated_at": "2026-02-28T08:17:21.000000Z"
  }
}
```

### Error Response
```json
{
  "error": "Error message",
  "status": 400
}
```

## Testing

Run tests:
```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/Api/PostControllerTest.php

# Run with coverage
php artisan test --coverage
```

## Deployment

### Production Checklist

1. Set `APP_DEBUG=false` in `.env`
2. Run `php artisan config:cache`
3. Run `php artisan route:cache`
4. Run `composer install --no-dev`
5. Run migrations: `php artisan migrate --force`
6. Set up proper database (MySQL/PostgreSQL)
7. Configure web server (Apache/Nginx)

### Environment Setup for Production

Create `.env` for production with:
```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=<generated-key>
DB_CONNECTION=mysql
DB_HOST=<your-host>
DB_DATABASE=<your-db>
DB_USERNAME=<your-user>
DB_PASSWORD=<your-password>
```

## Useful Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Laravel API Resources](https://laravel.com/docs/11.x/eloquent-resources)
- [RESTful API Best Practices](https://restfulapi.net/)

## Support & Troubleshooting

### Common Issues

**Port already in use:**
```bash
php artisan serve --port=8001
```

**Migrate command shows migration already ran:**
```bash
php artisan migrate:refresh  # Start fresh
```

**Cache issues:**
```bash
php artisan cache:clear
php artisan config:cache
```

## Next Steps

1. Implement authentication (Laravel Sanctum)
2. Add request validation
3. Create resource classes for consistent responses
4. Set up comprehensive testing
5. Configure CORS for frontend integration
6. Add API documentation (OpenAPI/Swagger)
