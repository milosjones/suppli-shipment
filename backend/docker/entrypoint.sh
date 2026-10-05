#!/bin/sh
set -e
cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ -z "${APP_KEY}" ] && ! grep -qE '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
fi

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    attempt=0
    until php artisan migrate --force --no-interaction; do
        attempt=$((attempt + 1))
        if [ "$attempt" -ge 20 ]; then
            echo "Database is not reachable, giving up." >&2
            exit 1
        fi
        echo "Waiting for the database (attempt $attempt)..."
        sleep 3
    done

    if [ "${SEED_ON_START:-false}" = "true" ]; then
        php artisan db:seed --force --no-interaction
    fi
fi

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
