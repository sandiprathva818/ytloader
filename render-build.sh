#!/usr/bin/env bash
# exit on error
set -o errexit

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Create bin directory for linux binaries
mkdir -p bin

# Download static ffmpeg for Linux if it doesn't exist
if [ ! -f bin/ffmpeg ]; then
    echo "Downloading static ffmpeg..."
    curl -L https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-amd64-static.tar.xz -o ffmpeg.tar.xz
    tar -xf ffmpeg.tar.xz --strip-components=1 -C bin/ ffmpeg-*-amd64-static/ffmpeg
    tar -xf ffmpeg.tar.xz --strip-components=1 -C bin/ ffmpeg-*-amd64-static/ffprobe
    rm ffmpeg.tar.xz
fi

# Download yt-dlp for Linux if it doesn't exist
if [ ! -f bin/yt-dlp ]; then
    echo "Downloading yt-dlp..."
    curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o bin/yt-dlp
    chmod a+rx bin/yt-dlp
fi

# Setup persistent storage
mkdir -p storage/framework/views
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/app/public/downloads

# Create sqlite database file if it doesn't exist
touch storage/database.sqlite

# Run database migrations
php artisan migrate --force

# Create symbolic link for public storage if it doesn't exist
if [ ! -L public/storage ]; then
    php artisan storage:link
fi

# Cache application configuration and routes for production speed
php artisan config:cache
php artisan route:cache
php artisan view:cache
