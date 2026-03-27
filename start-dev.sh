#!/bin/bash
# Laravel REST API Startup Script

# Navigate to project directory
cd "$(dirname "${BASH_SOURCE[0]}")"

echo "🚀 Starting Laravel REST API Server..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check if .env exists
if [ ! -f .env ]; then
    echo "⚠️  .env file not found. Creating from .env.example..."
    cp .env.example .env
fi

# Install dependencies if vendor folder doesn't exist
if [ ! -d vendor ]; then
    echo "📦 Installing dependencies..."
    composer install
fi

# Generate app key if not set
if ! grep -q "APP_KEY=base64:" .env; then
    echo "🔑 Generating application key..."
    php artisan key:generate
fi

# Run migrations
echo "🗄️  Running migrations..."
php artisan migrate --force

echo ""
echo "✅ Setup complete!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "📝 Available API Endpoints:"
echo "  • GET    /api/health              - Health check"
echo "  • GET    /api/posts               - List all posts"
echo "  • POST   /api/posts               - Create a new post"
echo "  • GET    /api/posts/{id}          - Get a specific post"
echo "  • PUT    /api/posts/{id}          - Update a post"
echo "  • DELETE /api/posts/{id}          - Delete a post"
echo ""
echo "🌐 Server: http://127.0.0.1:8000"
echo ""
php artisan serve --host=127.0.0.1 --port=8000
