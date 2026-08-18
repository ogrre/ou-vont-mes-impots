#!/bin/sh

set -eu

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

php artisan config:cache
php artisan route:cache
php artisan event:cache

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    if [ "${RUN_SEEDERS:-false}" = "true" ]; then
        php artisan migrate --force --seed
    else
        php artisan migrate --force
    fi
fi

if [ "${RUN_DATA_IMPORTS:-false}" = "true" ]; then
    php artisan dataset:import-known "${DATA_IMPORT_PATH:-data}"
fi

exec "$@"
