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

# 2. Ensure APP_KEY is set and valid in .env or environment
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "null" ]; then
    if ! grep -E -q '^APP_KEY=base64:[A-Za-z0-9+/=]{44}' .env 2>/dev/null; then
        echo "Generating application key..."
        php artisan key:generate --force
    fi
fi

# 3. Clear configuration cache so updated .env is always loaded
php artisan config:clear 2>/dev/null || true

# 4. Install dependencies if vendor directory is missing
if [ ! -f "/var/www/html/vendor/autoload.php" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --optimize-autoloader
fi

# 5. Run database migrations
echo "Running database migrations..."
php artisan migrate --force 2>/dev/null || true

# 6. Fix permissions for storage and cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Execute the main container process (php-fpm or queue worker)
exec "$@"
