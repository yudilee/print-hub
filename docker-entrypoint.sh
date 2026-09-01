#!/bin/bash
set -e

# Generate APP_KEY if not set
if [ -z "$APP_KEY" ]; then
    echo "No APP_KEY found. Generating a temporary one for this session..."
    php artisan key:generate --force --show --no-interaction
fi

# Ensure web server (www-data) has permissions for volumes
chown -R www-data:www-data storage
chmod -R 775 storage

# Create storage link if it doesn't exist
if [ ! -L public/storage ]; then
    echo "Creating storage symlink..."
    php artisan storage:link
fi

# Run migrations (Safe for Production as it only adds new columns/tables)
echo "Running database migrations..."
php artisan migrate --force --no-interaction

# Optimization (Clear caches for new code)
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan optimize

# Start Laravel Reverb WebSocket daemon in the background
echo "Starting Laravel Reverb WebSocket server..."
php artisan reverb:start --host=127.0.0.1 --port=8080 &

# Start Apache in the foreground (standard PHP-Apache entrypoint behavior)
echo "Starting Apache..."
exec apache2-foreground
