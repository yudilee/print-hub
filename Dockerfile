# Stage 1: Build Node.js assets (Vite)
FROM node:20-alpine AS node_assets
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# Stage 2: PHP Application
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libsqlite3-dev \
    libpq-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install -j$(nproc) pdo pdo_pgsql pdo_sqlite mbstring xml zip bcmath pcntl \
    && a2enmod rewrite proxy proxy_http proxy_wstunnel

# Increase PHP upload / POST size limits to handle large base64-encoded print payloads
RUN echo 'upload_max_filesize = 50M' > /usr/local/etc/php/conf.d/uploads.ini \
    && echo 'post_max_size = 50M' >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo 'max_execution_time = 120' >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo 'max_input_time = 120' >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo 'memory_limit = 256M' >> /usr/local/etc/php/conf.d/uploads.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
ENV COMPOSER_MEMORY_LIMIT=-1

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Copy Vite build assets from node_assets stage
COPY --from=node_assets /app/public/build ./public/build

# Update Apache configuration to point to /public and enable Reverb WebSocket proxying
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Set directory permissions for Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Install PHP dependencies
RUN composer install --no-scripts --no-autoloader --no-interaction --ignore-platform-reqs

# Generate optimized autoloader
RUN composer dump-autoload --optimize --no-scripts

# Entrypoint setup
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]

# Expose port (Internal)
EXPOSE 80
