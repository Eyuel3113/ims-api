#!/bin/sh
set -e

# Configure Apache port based on Render's $PORT environment variable (default to 80 if unset)
PORT="${PORT:-80}"
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:${PORT}>/g" /etc/apache2/sites-available/000-default.conf

# Create public storage symlink if not already present
php artisan storage:link --no-interaction || true

# Run database migrations FIRST so database tables (cache, sessions, users, etc.) exist
if [ -n "$DB_HOST" ] || [ -n "$DATABASE_URL" ] || [ "$DB_CONNECTION" = "sqlite" ]; then
    echo "Running database migrations..."
    php artisan migrate --force || echo "Warning: Migration failed. Check your database connection parameters."

    echo "Seeding initial realistic data..."
    php artisan db:seed --force || echo "Warning: Seeding encountered an issue."
fi

# Clear and refresh caches (safe now that database tables exist)
php artisan config:clear || true
php artisan cache:clear || true

# In production, cache config & routes for performance
if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

# Hand over process to Apache in foreground
exec apache2-foreground
