# syntax=docker/dockerfile:1.7

# The site as a container: one image, four stages, and the same image for development.
#
#   docker compose up -d --build                                          production shape
#   docker compose -f docker-compose.yml -f docker-compose.dev.yml up     development
#
# Build it after `php artisan webx:setup`: both lock files are what the image installs, and
# they are written by the setup that chose the modules.

# ---------- 1. Composer dependencies, without the dev ones ----------
FROM composer:2 AS vendor
WORKDIR /app

# The whole site first and then one install, rather than the manifest, an install and the
# rest: Composer does not skip a path repository whose directory is not there, it stops
# ("The url supplied for the path repository does not exist"), and a site that develops
# against a checkout of the packages beside it has one. Copying the manifest alone would put
# that directory outside the build for exactly the step that reads it. The layer this costs
# in caching the mount below gives back.
COPY . .

# The lock rather than the manifest, and it is committed for this reason: a deploy installs
# what was tested. A build that resolved afresh would pick up a release nobody has run yet,
# which is the one thing a deploy should never be the first to try.
#
# The platform is ignored because this is not the platform: the extensions the packages ask
# for are built into the runtime stage below, and the Composer image has almost none of them.
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install --no-dev --no-scripts --prefer-dist --no-interaction \
        --ignore-platform-reqs --optimize-autoloader --classmap-authoritative

# ---------- 2. The panel and the site's own styles, built by the site's own Vite ----------
FROM node:22-alpine AS assets
WORKDIR /app

COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm npm ci --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN mkdir -p storage/framework/views && npm run build

# ---------- 3. What runs: php-fpm, nginx and supervisor in one container ----------
FROM php:8.4-fpm-alpine AS app

# install-php-extensions resolves the Alpine build dependencies and builds sequentially;
# several `docker-php-ext-install` in parallel race on "cp: can't stat modules/*".
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# `mariadb-client` is what `webx:db:backup` shells out to, and it is the client of the server
# in docker-compose.yml. Against MySQL 8 instead it fails twice — on the self-signed
# certificate it is offered, and then on `caching_sha2_password`, whose plugin lives in a
# separate `mariadb-connector-c` package. Both are silent: the nightly dump writes no byte and
# the panel's "last snapshot" line simply stays empty.
#
# `redis` is built in although nothing here runs Redis: a site that measures a reason to move
# the cache, the queue or sessions onto it should be changing compose, not this file.
RUN apk add --no-cache nginx supervisor mariadb-client \
    && install-php-extensions pdo_mysql intl zip gd exif bcmath opcache pcntl redis

COPY docker/php.ini /usr/local/etc/php/conf.d/99-site.ini
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /app /var/www/html
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
        storage/logs storage/app/private storage/app/public bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80
ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]

# ---------- 4. The same container to develop in, and to run the tests in CI ----------
#
# docker-compose.dev.yml mounts the checkout over /var/www/html, which hides the vendor
# directory built above — so this stage carries Composer and installs into the mount on the
# first boot. The test suite needs the same thing: `docker build --target dev` then
# `php artisan test`.
FROM app AS dev

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.dev.ini /usr/local/etc/php/conf.d/99-site.ini

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apk add --no-cache git
