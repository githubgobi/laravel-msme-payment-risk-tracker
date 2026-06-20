#!/bin/sh
set -e

# Cache Laravel's config/routes/views when APP_KEY is present.
# Skip silently during CI or if KEY is missing — artisan will error otherwise.
if [ -n "$APP_KEY" ]; then
    php artisan config:cache  --quiet
    php artisan route:cache   --quiet
    php artisan view:cache    --quiet
    echo "[entrypoint] Config, route and view caches warmed."
fi

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
