#!/usr/bin/env bash
set -euo pipefail

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache || true
php artisan l5-swagger:generate --ansi || true

# Railpack / FrankenPHP (Caddy). Fallback artisan serve si absent.
if [[ -x /start-container.sh ]]; then
  exec /start-container.sh
fi

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
