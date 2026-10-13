#!/bin/sh
set -e

cd /var/www/html

# Without an APP_KEY, generate one once and keep it in the storage volume so
# sessions and encrypted values survive container restarts.
if [ -z "$APP_KEY" ]; then
    key_file=storage/app/private/.docker-app-key
    mkdir -p storage/app/private
    if [ ! -s "$key_file" ]; then
        php -r 'echo "base64:".base64_encode(random_bytes(32));' > "$key_file"
        chmod 600 "$key_file"
        echo "Generated APP_KEY (stored in the storage volume)."
    fi
    APP_KEY="$(cat "$key_file")"
    export APP_KEY
fi

mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache docs

echo "Waiting for the database at ${DB_HOST}:${DB_PORT:-3306}..."
tries=0
until php -r 'new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: 3306).";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));' 2>/dev/null; do
    tries=$((tries + 1))
    if [ "$tries" -ge 60 ]; then
        echo "Database not reachable after 120 seconds." >&2
        exit 1
    fi
    sleep 2
done

php artisan migrate --force

# Full seed (roles, options, templates, initial Super Admin, workbook inventory,
# shipped documents) only on the first start; afterwards only roles and
# permissions are refreshed, which keeps admin edits (see docs/DEPLOYMENT.md).
seed_marker=storage/app/private/.docker-seeded
if [ "${HUB_SEED_ON_FIRST_RUN:-true}" = "true" ] && [ ! -f "$seed_marker" ]; then
    php artisan db:seed --force
    touch "$seed_marker"
else
    php artisan db:seed --class=RolesAndPermissionsSeeder --force
fi

php artisan storage:link --force > /dev/null 2>&1 || true
php artisan optimize

# Files created by the commands above (logs, caches) must stay writable for Apache.
chown -R www-data:www-data storage bootstrap/cache

exec "$@"
