#!/bin/sh
set -eu

cd /var/www/html

if [ -z "${APP_URL:-}" ] && [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
    export APP_URL="$RENDER_EXTERNAL_URL"
fi

mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

php artisan package:discover --ansi
php artisan config:clear
php artisan migrate --force
php artisan app:bootstrap-demo-admin
if [ "${KIMIA_DEMO_ENABLED:-false}" = "true" ]; then
    php artisan demo:provision-kimia --force --no-interaction
fi
php artisan storage:link --force || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec apache2-foreground
