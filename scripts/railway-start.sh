#!/usr/bin/env bash
set -euo pipefail

# Service unique Railway (comme vos autres projets) :
# migrate → config:clear → queue:work (bg) → schedule:work (bg) → serve (fg)
#
# Équivalent Custom Start Command :
# php artisan migrate --force && php artisan config:clear && \
# php artisan queue:work --queue=broadcasts,emails,audits,media,default --sleep=3 --tries=3 --timeout=3600 & \
# php artisan schedule:work & \
# php artisan serve --host=0.0.0.0 --port=$PORT

# Railway Postgres expose DATABASE_URL ; Laravel utilise DB_URL.
if [[ -z "${DB_URL:-}" && -n "${DATABASE_URL:-}" ]]; then
  export DB_URL="$DATABASE_URL"
fi

if [[ -n "${DB_URL:-}${DATABASE_URL:-}" ]]; then
  export DB_CONNECTION="${DB_CONNECTION:-pgsql}"
fi

if [[ -z "${DB_URL:-}" && -z "${DATABASE_URL:-}" && -z "${DB_HOST:-}" ]]; then
  echo "ERROR: aucune base configurée. Sur Railway :"
  echo "  1) Ajoute un service PostgreSQL"
  echo "  2) Relie-le à l'API (Variables → Reference / Connect)"
  echo "  3) Définis DB_CONNECTION=pgsql"
  exit 1
fi

mkdir -p storage/api-docs storage/logs storage/framework/{cache,sessions,views}
chmod -R ug+rwx storage bootstrap/cache || true

php artisan migrate --force
php artisan config:clear

QUEUE_CONNECTION="${QUEUE_CONNECTION:-database}"
QUEUE_NAMES="${QUEUE_NAMES:-broadcasts,emails,audits,media,default}"

if [[ "$QUEUE_CONNECTION" == "redis" ]]; then
  php artisan queue:work redis --queue="$QUEUE_NAMES" --sleep=3 --tries=3 --timeout=3600 &
else
  php artisan queue:work --queue="$QUEUE_NAMES" --sleep=3 --tries=3 --timeout=3600 &
fi

php artisan schedule:work &

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
