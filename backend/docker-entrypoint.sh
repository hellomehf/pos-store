#!/bin/bash
set -e

# Run migrations
php artisan migrate --force

# Start the server
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
