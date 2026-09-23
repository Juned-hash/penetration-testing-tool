#!/bin/bash
set -e

export COMPOSER_ALLOW_SUPERUSER=1

# 1. Ensure .env file exists
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        echo "Creating .env from .env.example..."
        cp .env.example .env
    else
        touch .env
    fi
fi

# 2. Ensure APP_KEY is set in .env or environment
if [ -z "$APP_KEY" ]; then
    if ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
        echo "Generating application key..."
        php artisan key:generate --force
    fi
fi

# 3. Install dependencies if vendor directory is missing
if [ ! -f "/var/www/html/vendor/autoload.php" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --optimize-autoloader
fi

# 4. Run database migrations
echo "Running database migrations..."
php artisan migrate --force 2>/dev/null || true

# 5. Fix permissions for storage and cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Execute the main container process (php-fpm or queue worker)
exec "$@"
