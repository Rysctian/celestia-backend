#!/bin/sh
set -e

cd /var/www/html

# Only the php-fpm ("app") container prepares the application. Queue/scheduler
# and one-off commands (`docker compose run app php artisan ...`) skip setup.
is_fpm=false
case "$1" in
    php-fpm|*/php-fpm) is_fpm=true ;;
esac

if [ "$is_fpm" = "true" ] && [ "${RUN_SETUP:-true}" = "true" ]; then
    if [ -z "${APP_KEY}" ]; then
        echo "APP_KEY is not set. Generate one with: docker compose --env-file .env.docker run --rm --no-deps app php artisan key:generate --show" >&2
        exit 1
    fi

    echo "Waiting for database at ${DB_HOST}:${DB_PORT:-3306}..."
    until php -r '
        try {
            new PDO(
                "mysql:host=" . getenv("DB_HOST") . ";port=" . (getenv("DB_PORT") ?: 3306),
                getenv("DB_USERNAME"),
                getenv("DB_PASSWORD")
            );
        } catch (Throwable $e) { exit(1); }
    '; do
        sleep 2
    done

    # Menus/roles are created by migrations; sessions, cache and queue tables too
    # (SESSION_DRIVER, CACHE_STORE and QUEUE_CONNECTION are all "database").
    php artisan migrate --force

    # Idempotent: seeds the menu catalog and role grants without touching edits.
    php artisan db:seed --class=MenuAccessSeeder --force

    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

exec "$@"
