#!/bin/sh
set -eu

cd /app

mkdir -p /srv/job-tracker-data

composer install --no-interaction --prefer-dist
php bin/console doctrine:migrations:migrate --no-interaction

exec frankenphp run --config /etc/caddy/Caddyfile
