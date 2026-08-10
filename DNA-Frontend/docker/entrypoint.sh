#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

# The compose healthcheck already gates on MySQL accepting connections, but the
# grant for the application user can land a moment later. Retrying here turns a
# crash-loop on first boot into a few seconds of waiting.
attempt=0
until php artisan migrate --force --no-interaction 2>/dev/null; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 30 ]; then
        echo "Database did not become ready in time." >&2
        exit 1
    fi
    echo "Waiting for the database (attempt ${attempt})…"
    sleep 2
done

# Cache after migrating so a failed migration does not leave stale caches behind.
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
