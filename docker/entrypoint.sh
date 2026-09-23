#!/bin/bash
set -e

export COMPOSER_ALLOW_SUPERUSER=1

# Install dependencies if vendor directory is missing
if [ ! -f "/var/www/html/vendor/autoload.php" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --optimize-autoloader
fi

# Generate APP_KEY if not set
if [ -z "$APP_KEY" ] && [ -f ".env" ] && grep -q "^APP_KEY=$" .env; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Run migrations
echo "Running migrations..."
php artisan migrate --force 2>/dev/null || true

# Fix permissions
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Execute the original command (php-fpm or queue:work)
exec "$@"
