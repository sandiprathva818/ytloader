FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libsqlite3-dev \
    zip \
    unzip \
    sqlite3 \
    python3 \
    ffmpeg

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_sqlite mbstring pcntl bcmath

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js & npm (for Vite)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Create bin directory for yt-dlp
RUN mkdir -p /usr/local/bin

# Install yt-dlp
RUN curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o /usr/local/bin/yt-dlp
RUN chmod a+rx /usr/local/bin/yt-dlp

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Install NPM dependencies and build Node assets
RUN npm install
RUN npm run build

# Copy the startup script for Render's empty persistent disk
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Note: We do NOT run config:cache or migrations here.
# Render mounts an empty Persistent Disk over /var/www/html/storage at runtime,
# which completely deletes/hides all folders and databases created during build.
# We rebuild them using docker-entrypoint.sh instead.

# Note: We intentionally DO NOT run php artisan config:cache during build.
# Docker build does not have access to Render runtime environment variables (like APP_KEY).
# Caching during build would permanently lock in empty configs.

# Expose port 8000
EXPOSE 8000

# Set entrypoint to run the startup script first
ENTRYPOINT ["docker-entrypoint.sh"]

# Start Laravel's built-in server
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
