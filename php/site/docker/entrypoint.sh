#!/bin/sh
set -e

cd /var/www/html

# A development container has the checkout mounted over this directory, which hides the vendor
# the image was built with. Nothing to do in production, where the mount is not there.
if [ ! -f vendor/autoload.php ] && command -v composer >/dev/null 2>&1; then
    echo "No vendor directory under the mount — installing. The first boot takes a few minutes."
    composer install --no-interaction --prefer-dist
fi

# Storage is a volume, and a volume takes its contents from the image once — the first time it
# is used and never again. So a release that starts writing somewhere new under storage finds
# nothing there on a site that has been running since before it: the directory is simply
# missing, and what fails is whatever first tried to write into it.
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
    storage/logs storage/app/private storage/app/public

# What registers the packages' service providers, and therefore what has to run before any of
# their commands — `webx:boot` included, which is one of them.
php artisan package:discover --ansi

# Everything else: waiting for the database, migrating, seeding what travels as files, and
# caching. Which steps those are depends on which modules are installed, so it is a command in
# the package rather than a list here — `php artisan webx:boot --pretend` prints it.
# shellcheck disable=SC2086
php artisan webx:boot ${WEBX_BOOT_OPTIONS:-}

# The boot ran as root, and php-fpm does not.
chown -R www-data:www-data storage bootstrap/cache

exec "$@"
