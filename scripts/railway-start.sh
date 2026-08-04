#!/usr/bin/env bash
set -euo pipefail

# Railway Postgres expose DATABASE_URL ; Laravel utilise DB_URL.
if [[ -z "${DB_URL:-}" && -n "${DATABASE_URL:-}" ]]; then
  export DB_URL="$DATABASE_URL"
fi

# Force pgsql en prod si une URL Postgres est présente.
if [[ -n "${DB_URL:-}${DATABASE_URL:-}" ]]; then
  export DB_CONNECTION="${DB_CONNECTION:-pgsql}"
fi

if [[ -z "${DB_URL:-}" && -z "${DATABASE_URL:-}" && -z "${DB_HOST:-}" ]]; then
  echo "ERROR: aucune base configurée. Sur Railway :"
  echo "  1) Ajoute un service PostgreSQL"
  echo "  2) Relie-le à l'API (Variables → Reference / Connect)"
  echo "  3) Définis DB_CONNECTION=pgsql"
  echo "  4) Ou mappe DATABASE_URL → variable partagée"
  exit 1
fi

mkdir -p storage/api-docs storage/logs storage/framework/{cache,sessions,views}
chmod -R ug+rwx storage bootstrap/cache || true

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
