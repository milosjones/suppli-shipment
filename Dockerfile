# Production image: React build + Laravel API served by FrankenPHP, with automatic HTTPS when
# SERVER_NAME is a domain. Run it with docker-compose.prod.yml; local development keeps using docker-compose.yml.

FROM node:22-alpine AS frontend
WORKDIR /app

COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY frontend/ .
RUN npm run build

FROM dunglas/frankenphp:1-php8.4-alpine

RUN install-php-extensions pdo_pgsql zip gd intl bcmath opcache pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=pgsql \
    CACHE_STORE=database \
    QUEUE_CONNECTION=database \
    SESSION_DRIVER=array \
    TRACKING_PROVIDERS=fake \
    PORT=8080

WORKDIR /app

COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader --no-progress

COPY backend/ .
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && mkdir -p storage/app/private/imports storage/framework/cache storage/framework/views storage/logs

COPY backend/docker/php.ini $PHP_INI_DIR/conf.d/zz-app.ini
COPY --from=frontend /app/dist /srv/spa
COPY deploy/Caddyfile /etc/frankenphp/Caddyfile
COPY deploy/start.sh /usr/local/bin/start
RUN chmod +x /usr/local/bin/start

EXPOSE 80 443
ENTRYPOINT ["start"]
