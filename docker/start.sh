#!/bin/sh
set -eu

cd /var/www/html
# Render mounts secrets for root. Give only the PHP runtime user a private, runtime-only copy.
if [ -n "${FIREBASE_CREDENTIALS:-}" ]; then
    if [ ! -r "$FIREBASE_CREDENTIALS" ]; then
        echo "[ZAYLO startup] Firebase credential file is not readable" >&2
        exit 1
    fi
    install -d -o www-data -g www-data -m 700 /run/zaylo
    install -o www-data -g www-data -m 600 "$FIREBASE_CREDENTIALS" /run/zaylo/firebase-service-account.json
    export FIREBASE_CREDENTIALS=/run/zaylo/firebase-service-account.json
fi
# Render Free has no shell or pre-deploy command. Apply pending migrations before serving requests.
echo "[ZAYLO startup] Applying pending database migrations"
php artisan migrate --force --no-interaction
echo "[ZAYLO startup] Checking deployment configuration"
php artisan deployment:check --no-interaction
echo "[ZAYLO startup] Registered scheduled tasks"
php artisan schedule:list --no-interaction
echo "[ZAYLO startup] Starting web server and scheduler"
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/zaylo.conf
