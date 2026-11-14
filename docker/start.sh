#!/bin/sh
set -eu

cd /var/www/html
# Render Free has no shell or pre-deploy command. Apply pending migrations before serving requests.
echo "[ZAYLO startup] Applying pending database migrations"
php artisan migrate --force --no-interaction
echo "[ZAYLO startup] Checking deployment configuration"
php artisan deployment:check --no-interaction
echo "[ZAYLO startup] Registered scheduled tasks"
php artisan schedule:list --no-interaction
echo "[ZAYLO startup] Starting web server and scheduler"
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/zaylo.conf
