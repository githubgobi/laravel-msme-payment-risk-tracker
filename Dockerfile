# ─── Stage 1: Composer dependencies ─────────────────────────────────────────
FROM composer:2.7 AS deps

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ─── Stage 2: Node / Vite assets ─────────────────────────────────────────────
FROM node:20-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts

COPY . .
RUN npm run build

# ─── Stage 3: Production image ────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS production

# System packages — nginx + supervisor run alongside php-fpm in one container
RUN apk add --no-cache \
    nginx \
    supervisor \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    mysql-client

# PHP extensions required by Laravel + this project
RUN docker-php-ext-install -j"$(nproc)" \
    pdo_mysql \
    mbstring \
    intl \
    zip \
    bcmath \
    opcache \
    pcntl

# OPcache — validate_timestamps=0 requires a container rebuild to pick up code
# changes, which is the correct deployment model (immutable images).
RUN { \
    echo "opcache.enable=1"; \
    echo "opcache.memory_consumption=128"; \
    echo "opcache.max_accelerated_files=10000"; \
    echo "opcache.validate_timestamps=0"; \
    echo "opcache.revalidate_freq=0"; \
    echo "opcache.fast_shutdown=1"; \
} > /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

# Copy application code first, then overlay vendor + compiled assets from
# earlier stages — this preserves correct file ownership without a second pass.
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=deps   /app/vendor         ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build   ./public/build

# Infrastructure configs
COPY docker/nginx.conf       /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh    /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Storage directories — created here so they exist before volume mounts
RUN mkdir -p \
        storage/logs \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
