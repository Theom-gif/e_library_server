@echo off
REM Laravel REST API Startup Script for Windows

cd /d "%~dp0"

echo.
echo 🚀 Starting Laravel REST API Server...
echo ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo.

REM Check if .env exists
if not exist .env (
    echo ⚠️  .env file not found. Creating from .env.example...
    copy .env.example .env
)

REM Check if dependencies are installed
if not exist vendor (
    echo 📦 Installing dependencies...
    call composer install
)

REM Generate app key if not set
findstr "APP_KEY=base64:" .env >nul
if errorlevel 1 (
    echo 🔑 Generating application key...
    php artisan key:generate
)

REM Run migrations
echo 🗄️  Running migrations...
php artisan migrate --force

echo.
echo ✅ Setup complete!
echo ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo.
echo 📝 Available API Endpoints:
echo   • GET    /api/health              - Health check
echo   • GET    /api/posts               - List all posts
echo   • POST   /api/posts               - Create a new post
echo   • GET    /api/posts/{id}          - Get a specific post
echo   • PUT    /api/posts/{id}          - Update a post
echo   • DELETE /api/posts/{id}          - Delete a post
echo.
echo 🌐 Server: http://127.0.0.1:8000
echo.
echo Press Ctrl+C to stop the server
echo.
php -d upload_max_filesize=50M -d post_max_size=50M -d memory_limit=256M artisan serve --host=127.0.0.1 --port=8000
