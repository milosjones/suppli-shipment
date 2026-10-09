#!/bin/sh
set -e
cd /app

export SERVER_NAME="${SERVER_NAME:-:${PORT:-8080}}"
case "$SERVER_NAME" in
    :*) export APP_URL="${APP_URL:-http://localhost${SERVER_NAME}}" ;;
    *) export APP_URL="${APP_URL:-https://${SERVER_NAME}}" ;;
esac

php artisan config:cache
php artisan route:cache
php artisan event:cache

attempt=0
until php artisan migrate --force --no-interaction; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 10 ]; then
        echo "Database is not reachable, giving up." >&2
        exit 1
    fi
    echo "Waiting for the database (attempt $attempt)..."
    sleep 3
done

if [ "${SEED_ON_START:-false}" = "true" ]; then
    php artisan db:seed --force --no-interaction
fi

(while true; do php artisan queue:work --sleep=3 --max-time=3600 || sleep 5; done) &
(while true; do php artisan schedule:work || sleep 5; done) &

exec frankenphp run --config /etc/frankenphp/Caddyfile
