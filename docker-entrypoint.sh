#!/usr/bin/env bash

# This script runs EVERY time the container starts.
# We must do this at startup because Render mounts a blank Persistent Disk over /var/www/html/storage
# which deletes/hides all the folders we created during Docker build.

echo "Initializing persistent storage..."

# Ensure storage directories exist on the persistent disk
mkdir -p storage/framework/views
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/app/public/downloads
mkdir -p storage/app/tmp
mkdir -p storage/logs

# Fix permissions
chmod -R 777 storage bootstrap/cache

# Create SQLite database if it doesn't exist
touch storage/database.sqlite
chmod 777 storage/database.sqlite

# Run database migrations
php artisan migrate --force

# Link storage
php artisan storage:link || true

echo "Starting background queue worker..."
php artisan queue:listen --tries=3 --timeout=3600 &

echo "Starting server..."
exec "$@"
