#!/usr/bin/env sh
set -eu

cd /var/www/html

if [ "${APP_ENV:-local}" = "production" ]; then
  if [ -z "${APP_KEY:-}" ] || [ "${APP_KEY}" = "base64:39eZ4r6pAnMqoEThQIRt18frP6mR3mw8N9o5aPv+Ag8=" ]; then
    echo "Refusing to start production with a missing or example APP_KEY." >&2
    exit 1
  fi
  if [ "${APP_DEBUG:-false}" = "true" ]; then
    echo "Refusing to start production while APP_DEBUG=true." >&2
    exit 1
  fi
fi

if [ ! -f vendor/autoload.php ]; then
  echo "Laravel vendor directory is missing; installing dependencies..."
  composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader
fi

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R ug+rw storage bootstrap/cache || true

if [ "${WAIT_FOR_DB:-true}" = "true" ] && [ "${DB_CONNECTION:-mysql}" = "mysql" ]; then
  echo "Waiting for MySQL..."
  until mysqladmin ping -h"${DB_HOST:-mysql}" -P"${DB_PORT:-3306}" -u"${DB_USERNAME:-oopseller}" -p"${DB_PASSWORD:-oopseller}" --silent; do
    sleep 2
  done
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force
fi

if [ "${RUN_SEEDERS:-false}" = "true" ]; then
  php artisan db:seed --force
fi

exec "$@"
