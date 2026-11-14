#!/bin/sh
set -eu

cd /var/www/html
# Refuse a new container with ephemeral uploads or unfinished migrations.
php artisan deployment:check --no-interaction
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/zaylo.conf
