#!/bin/sh
# Entrypoint image production. Cache konfigurasi, rute, dan view dibuat saat container start,
# karena environment production baru tersedia di sini (image tidak membawa file .env).
set -eu

if [ -z "${APP_KEY:-}" ]; then
  echo "APP_KEY kosong: isi lewat environment container." >&2
  exit 1
fi

cd /var/www/html
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec docker-php-entrypoint "$@"
