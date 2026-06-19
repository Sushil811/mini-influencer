# === Stage 1: Base PHP image with all extensions ===
FROM php:8.3-fpm-alpine AS php-base
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    bash \
    curl \
    libpq \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_pgsql pdo_mysql bcmath zip gd opcache intl \
    && apk del .build-deps

# === Stage 2: Install Composer Dependencies ===
FROM composer:2.7 AS composer-builder
WORKDIR /app
COPY composer*.json ./
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist

# === Stage 3: Build Frontend Assets ===
FROM php-base AS assets-builder
RUN apk add --no-cache nodejs npm
WORKDIR /app
COPY . .
COPY --from=composer-builder /app/vendor ./vendor
RUN npm ci && npm run build

# === Stage 4: Production Runtime ===
FROM php-base AS runtime

# Install supervisor and nginx
RUN apk add --no-cache supervisor nginx

# Set working directory
WORKDIR /var/www/html

# Copy Nginx, PHP, and Supervisor configurations
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/opcache.ini $PHP_INI_DIR/conf.d/opcache.ini

# Copy application files
COPY --chown=www-data:www-data . .

# Copy compiled assets from assets-builder
COPY --from=assets-builder --chown=www-data:www-data /app/public/build ./public/build

# Copy vendor folder from composer-builder
COPY --from=composer-builder --chown=www-data:www-data /app/vendor ./vendor

# Configure permissions for storage and bootstrap cache
RUN chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Expose port
EXPOSE 80

# Copy and set entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
