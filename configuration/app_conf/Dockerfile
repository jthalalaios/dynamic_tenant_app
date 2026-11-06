# Single source of truth for the PHP runtime (latest PHP 8.5 FPM on Debian 13 "trixie")
ARG PHP_IMAGE=php:8.5.12-fpm-trixie

# =========================
# Stage 1: PHP Composer Builder
# =========================
FROM ${PHP_IMAGE} AS composer_builder

WORKDIR /app

# Install system dependencies for Laravel & PHP extensions
RUN apt-get update && apt-get install -y \
    git unzip zip curl libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libonig-dev libxml2-dev libzip-dev libpq-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring bcmath exif pcntl gd zip intl \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy Laravel code
COPY laravel-app/ .

# Install Composer dependencies (with dev for development)
RUN composer install --prefer-dist --optimize-autoloader

# =========================
# Stage 2: Final PHP-FPM image
# =========================
FROM ${PHP_IMAGE}

WORKDIR /var/www/html

# Install PHP extensions and dependencies (NO Node.js here)
RUN apt-get update && apt-get install -y \
    git curl unzip zip \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libonig-dev libxml2-dev libzip-dev \
    libpq-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo pdo_pgsql pgsql mbstring bcmath exif pcntl gd zip intl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Copy application code
COPY laravel-app/ /var/www/html

# Remove uploaded/stored images — these should be provided via volume at runtime
RUN rm -rf /var/www/html/public/uploads

# delete any pre-generated config/packages caches that might have been
# committed to the repo; we always rebuild them at runtime so the
# container should start with a clean slate.
RUN rm -rf /var/www/html/bootstrap/cache/* || true

# Copy vendor from Composer stage
COPY --from=composer_builder /app/vendor ./vendor

# Copy Composer binary so the entrypoint can run `composer install`
# when the vendor folder is wiped (e.g. bind-mount without vendor present)
COPY --from=composer_builder /usr/local/bin/composer /usr/local/bin/composer

# /bin/sh (dash) has no brace expansion, so list each directory explicitly
RUN mkdir -p storage/framework/views storage/framework/cache storage/framework/sessions \
        storage/logs storage/app/public bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chmod -R 777 storage/framework/sessions \
    && chmod 1777 /tmp

# Optional: set PHP system temp dir explicitly
RUN echo "sys_temp_dir=/tmp" >> /usr/local/etc/php/conf.d/temp.ini

COPY configuration/app_conf/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose PHP-FPM port
EXPOSE 9000

# Use entrypoint
ENTRYPOINT ["entrypoint.sh"]
