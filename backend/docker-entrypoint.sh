#!/bin/bash
set -e

echo "Waiting for database connection..."
until php -r "try { new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); exit(0); } catch (Exception \$e) { exit(1); }"; do
    echo "Database is unavailable - sleeping 2 seconds..."
    sleep 2
done
echo "Database is ready!"

# Create storage symlink if not already present
php artisan storage:link || true

# Run database migrations
php artisan migrate --force

# Start Laravel server
echo "Starting Laravel backend on port ${PORT:-8000}..."
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
