#!/bin/sh
set -e

# Configure Apache port based on Render's $PORT environment variable (default to 80 if unset)
PORT="${PORT:-80}"
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:${PORT}>/g" /etc/apache2/sites-available/000-default.conf

# Create public storage symlink if not already present
php artisan storage:link --no-interaction || true

# Clear previous caches
php artisan config:clear
php artisan cache:clear

# In production, cache config & routes for performance
if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

# Run database migrations if database connection is configured
if [ -n "$DB_HOST" ] || [ -n "$DATABASE_URL" ] || [ "$DB_CONNECTION" = "sqlite" ]; then
    echo "Running database migrations..."
    php artisan migrate --force || echo "Warning: Migration failed. Check your database connection parameters."
fi

# Hand over process to Apache in foreground
exec apache2-foreground
