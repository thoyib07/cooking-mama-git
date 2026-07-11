# ---- Stage 1: build frontend assets ----
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

# ---- Stage 2: PHP application ----
FROM php:8.3-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libzip-dev libicu-dev unzip git \
    && docker-php-ext-install pdo_pgsql pgsql bcmath zip intl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist \
    && chmod -R 775 storage bootstrap/cache

ENV PORT=10000
EXPOSE 10000

CMD ["sh", "-c", "php artisan migrate --force && php artisan db:seed --class=AdminSeeder --force && php artisan config:cache && php artisan serve --host=0.0.0.0 --port=${PORT}"]
