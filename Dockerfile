# Stage 1: Build Frontend Assets
FROM node:20-bookworm AS assets-builder

WORKDIR /app

# Install PHP-cli, git, and unzip to compile dependencies
RUN apt-get update && apt-get install -y php-cli php-zip php-xml php-curl php-mbstring unzip git \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy local source code instead of git cloning
COPY . .

# Install PHP dependencies so artisan can boot
RUN composer install --no-dev --ignore-platform-reqs --no-scripts --prefer-dist

# Install JS dependencies & build assets
RUN npm ci --ignore-scripts
RUN npm run build


# ─────────────────────────────────────────────────────────────────────────────
# Stage 2: Pure Debian Bookworm PHP 8.4 Production Image
# ─────────────────────────────────────────────────────────────────────────────
FROM php:8.4-cli-bookworm

WORKDIR /var/www/html

ENV APP_ENV=production
ENV DEBIAN_FRONTEND=noninteractive

# ── System dependencies ──────────────────────────────────────────────────────
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    libzip-dev \
    libpq-dev \
    zip \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# ── Install PHP Extensions ────────────────────────────────────────────────────
# Use official docker-php-extension-installer script
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions \
    pdo_pgsql \
    pdo_sqlite \
    mbstring \
    zip \
    exif \
    pcntl \
    bcmath \
    gd \
    opcache \
    intl \
    redis

# ── PHP Configuration ─────────────────────────────────────────────────────────
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini $PHP_INI_DIR/conf.d/99-docudrive.ini

# ── Composer ──────────────────────────────────────────────────────────────────
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ── Application Source & Assets ────────────────────────────────────────────────
COPY --from=assets-builder /app .

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_HTTP2=0
RUN composer install --no-dev --optimize-autoloader

# ── Permissions ───────────────────────────────────────────────────────────────
RUN mkdir -p database storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

# ── Entrypoint ────────────────────────────────────────────────────────────────
COPY docker/watchtower-entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Web server port
EXPOSE 8000

ENTRYPOINT ["entrypoint.sh"]
