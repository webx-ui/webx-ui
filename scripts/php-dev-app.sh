#!/usr/bin/env bash
#
# Provision a Laravel application to develop the admin front end against.
#
# The panel is a single-page application talking to the packages in this checkout, and the only
# way to find out whether cookies, CSRF and 401s behave is to have a real backend answering.
# This sets one up once; after that `php artisan serve` in it is all you need.
#
#   scripts/php-dev-app.sh                     # ../webx-cms.local, sqlite
#   APP_PATH=/somewhere scripts/php-dev-app.sh
#
# It is deliberately not idempotent about an existing application: recreating one would throw
# away whatever you put in it.

set -euo pipefail

MONOREPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP="${APP_PATH:-$(cd "$MONOREPO/.." && pwd)/webx-cms.local}"

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

ADMIN_NAME="${ADMIN_NAME:-Developer}"
ADMIN_EMAIL="${ADMIN_EMAIL:-dev@webx.test}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-correct-horse-battery-staple}"

step() { printf '\n\033[1m== %s\033[0m\n' "$1"; }
note() { printf '   %s\n' "$1"; }

if [ -d "$APP" ]; then
    printf 'There is already an application at %s.\n' "$APP" >&2
    printf 'Delete it yourself if you want a fresh one — this script will not.\n' >&2
    exit 1
fi

step "A Laravel application in $APP"
$COMPOSER_BIN create-project laravel/laravel "$APP" --no-interaction --prefer-dist --quiet
note "$($PHP_BIN "$APP/artisan" --version)"

step "Point it at the packages in this checkout"
# The same repository definition the development root uses, so the pinned versions cannot
# drift; only the path differs, because it is relative to the application.
REPOSITORY="$(
    "$PHP_BIN" -r '
        $root = json_decode(file_get_contents($argv[1]), true);
        foreach ($root["repositories"] as $repository) {
            if (($repository["type"] ?? "") === "path") {
                $repository["url"] = $argv[2]."/php/packages/*";
                unset($repository["options"]["//"]);
                echo json_encode($repository);
                exit;
            }
        }
        exit(1);
    ' "$MONOREPO/php/composer.json" "$MONOREPO"
)"

(
    cd "$APP"
    $COMPOSER_BIN config repositories.webx "$REPOSITORY"
    # Symlinked, so editing a package in the monorepo is visible here without reinstalling.
    $COMPOSER_BIN config repositories.packagist.org \
        '{"type":"composer","url":"https://repo.packagist.org","exclude":["webx-ui/*"]}'
    $COMPOSER_BIN require webx-ui/module-auth:'*' --no-interaction --no-progress --quiet
)
note 'webx-ui/admin, webx-ui/mcp and webx-ui/module-auth linked to the checkout'

step "Configure it"
set_env() {
    "$PHP_BIN" -r '
        [, $file, $key, $value] = $argv;
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        $written = false;
        foreach ($lines as $index => $line) {
            if (str_starts_with($line, $key."=")) {
                $lines[$index] = $key."=".$value;
                $written = true;
            }
        }
        if (! $written) {
            $lines[] = $key."=".$value;
        }
        file_put_contents($file, implode("\n", $lines)."\n");
    ' "$APP/.env" "$1" "$2"
}

# Laravel reads .env immutably — the first assignment of a name wins — so these replace rather
# than append.
set_env APP_NAME WebX
set_env APP_URL "http://127.0.0.1:8000"
set_env DB_CONNECTION sqlite
set_env SESSION_DRIVER database

: > "$APP/database/database.sqlite"

"$PHP_BIN" "$APP/artisan" migrate --force --no-interaction --quiet
note 'migrated onto sqlite'

step "Create an administrator"
WEBX_ADMIN_PASSWORD="$ADMIN_PASSWORD" "$PHP_BIN" "$APP/artisan" webx:admin \
    --name="$ADMIN_NAME" --email="$ADMIN_EMAIL" --super --no-interaction
note "$ADMIN_EMAIL / $ADMIN_PASSWORD"

cat <<INSTRUCTIONS

$(printf '\033[32m== Ready.\033[0m')

   Serve it:   php artisan serve            (from $APP)
   Panel:      http://127.0.0.1:8000/cms
   Manifest:   http://127.0.0.1:8000/api/cms/manifest

The front end is developed against this through a Vite proxy, so the browser only ever talks to
one origin and the session cookie behaves the way it will in production.

INSTRUCTIONS
