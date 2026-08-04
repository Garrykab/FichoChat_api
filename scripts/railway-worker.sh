#!/usr/bin/env bash
set -euo pipefail

exec php artisan queue:work \
  --queue=broadcasts,emails,audits,media,default \
  --sleep=3 \
  --tries=3 \
  --timeout=3600 \
  --max-time=3600
