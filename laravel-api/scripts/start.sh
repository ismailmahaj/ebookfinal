#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

php artisan migrate --force
php artisan storage:link --force 2>/dev/null || true

if [ "${APP_ENV:-production}" = "production" ]; then
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
fi

exec php -c php-prod.ini artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
