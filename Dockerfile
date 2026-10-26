# syntax=docker/dockerfile:1.7
ARG PHP_VERSION=8.4
ARG NODE_VERSION=22

# ── Stage 1: dependensi Composer (tanpa dev) ────────────────────
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# Platform check dilewati di sini; ekstensi diverifikasi di stage runtime.
RUN composer install \
      --no-dev --no-scripts --no-autoloader \
      --prefer-dist --no-interaction --no-progress \
      --ignore-platform-reqs

# ── Stage 2: build aset frontend (Vite) ─────────────────────────
FROM node:${NODE_VERSION}-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
# Beberapa paket JS (mis. Ziggy) mengimpor dari vendor/.
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# ── Stage 2b: nginx production (aset statis di-bake; PHP diteruskan ke php-fpm di service `app`) ──
# Ditaruh sebelum stage production supaya `docker build` tanpa --target tetap menghasilkan image aplikasi.
FROM nginx:1.27-alpine AS nginx-production
RUN apk upgrade --no-cache
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=assets /app/public /var/www/html/public

# ── Stage 3: base runtime (dipakai development & production) ────
FROM php:${PHP_VERSION}-fpm-alpine AS base
COPY --from=mlocati/php-extension-installer:latest \
     /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql redis pcntl bcmath intl zip opcache

# Patch keamanan paket Alpine yang sudah dirilis repositori (mis. brotli-libs CVE-2026-111497,
# curl CVE-2026-92392), selama image dasar PHP belum memuatnya. Menjadi no-op setelah image dasar menyusul.
RUN apk upgrade --no-cache
WORKDIR /var/www/html

# ── Stage 4: development (kode di-mount dari host) ──────────────
FROM base AS development
# Samakan UID/GID www-data dengan user host agar bind mount bisa ditulis.
# Override: docker compose build --build-arg UID=$(id -u) --build-arg GID=$(id -g)
ARG UID=1000
ARG GID=1000
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apk add --no-cache git unzip shadow \
 && groupmod -o -g ${GID} www-data \
 && usermod -o -u ${UID} www-data
# php-fpm berjalan non-root; peringatan "user directive is ignored" itu normal.
USER www-data

# ── Stage 5: production (kode dan dependensi di-bake) ───────────
FROM base AS production
RUN { \
      echo 'expose_php=Off'; \
      echo 'memory_limit=256M'; \
      echo 'opcache.enable=1'; \
      echo 'opcache.memory_consumption=128'; \
      echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/zz-production.ini

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# Composer hanya dipinjam saat build, tidak masuk image akhir.
RUN --mount=from=composer:2,source=/usr/bin/composer,target=/usr/bin/composer \
    composer dump-autoload --no-dev --classmap-authoritative --no-interaction \
 && mkdir -p storage/framework/cache storage/framework/sessions \
             storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

# config:cache, route:cache, dan view:cache dijalankan saat container start (butuh env runtime)
# oleh entrypoint di bawah. Migrasi tetap langkah deploy tersendiri.
COPY --chmod=755 docker/php/entrypoint-production.sh /usr/local/bin/docker-entrypoint-production
USER www-data
# ENTRYPOINT di image turunan mereset CMD bawaan, jadi CMD ditulis ulang.
ENTRYPOINT ["docker-entrypoint-production"]
CMD ["php-fpm"]
