#!/usr/bin/env bash
#
# Install the PHP packages into a real Laravel application and check that the panel behaves.
#
# Shallow on purpose: the 92 tests in php/ cover the logic, and they run under Testbench, where
# the providers are wired by hand, the database is sqlite in memory and CSRF is switched off.
# This covers what that cannot — package discovery, a real database, a real session with a real
# CSRF token, and the config and route caches a production deploy turns on.
#
# Locally:
#   scripts/php-smoke.sh
# Against MySQL, the way CI runs it:
#   DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=webx DB_USERNAME=root DB_PASSWORD=secret \
#     scripts/php-smoke.sh

set -euo pipefail

MONOREPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WORKDIR="${SMOKE_DIR:-$(mktemp -d)}"
APP="$WORKDIR/app"

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

DB_CONNECTION="${DB_CONNECTION:-sqlite}"
PORT="${SMOKE_PORT:-8123}"
BASE="http://127.0.0.1:${PORT}"

ADMIN_EMAIL="smoke@example.test"
ADMIN_PASSWORD="correct-horse-battery-staple"

COOKIES="$WORKDIR/cookies.txt"
SERVER_PID=""

step() { printf '\n\033[1m== %s\033[0m\n' "$1"; }
note() { printf '   %s\n' "$1"; }
fail() { printf '\n\033[31mFAILED: %s\033[0m\n' "$1" >&2; exit 1; }

cleanup() {
    if [ -n "$SERVER_PID" ] && kill -0 "$SERVER_PID" 2>/dev/null; then
        kill "$SERVER_PID" 2>/dev/null || true
        wait "$SERVER_PID" 2>/dev/null || true
    fi
}
trap cleanup EXIT

status() {
    curl -s -o /dev/null -w '%{http_code}' -c "$COOKIES" -b "$COOKIES" -H 'Accept: application/json' "$@"
}

expect() {
    local expected="$1" actual="$2" what="$3"
    [ "$actual" = "$expected" ] || fail "$what — expected $expected, got $actual"
    note "$what -> $actual"
}

# --------------------------------------------------------------------------------------------

step "A fresh Laravel application in $APP"
mkdir -p "$WORKDIR"
$COMPOSER_BIN create-project laravel/laravel "$APP" --no-interaction --prefer-dist --quiet
note "$($PHP_BIN "$APP/artisan" --version)"

step "Point it at the packages in this checkout"
# Reusing the development root's repository definition keeps the pinned versions from drifting;
# only the path has to change, because it is relative to the application.
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
        fwrite(STDERR, "no path repository in php/composer.json\n");
        exit(1);
    ' "$MONOREPO/php/composer.json" "$MONOREPO"
)"

(
    cd "$APP"
    $COMPOSER_BIN config repositories.webx "$REPOSITORY"
    # Otherwise Composer could serve the released version of a package instead of the one in
    # this checkout, and the whole run would prove nothing about the change under test.
    $COMPOSER_BIN config repositories.packagist.org \
        '{"type":"composer","url":"https://repo.packagist.org","exclude":["webx-ui/*"]}'
    $COMPOSER_BIN require webx-ui/module-auth:'*' --no-interaction --no-progress --quiet
)

step "The packages came from the checkout, not from Packagist"
for package in admin mcp module-auth; do
    [ -L "$APP/vendor/webx-ui/$package" ] || [ -f "$APP/vendor/webx-ui/$package/.git" ] \
        || fail "vendor/webx-ui/$package is a copy, so a released version was installed instead of this checkout"
    note "webx-ui/$package is linked to the checkout"
done

step "Providers are found by discovery, not by hand"
# Nothing in this script registers them. If extra.laravel.providers is wrong, this is where it
# shows — the tests register providers explicitly and can never notice.
"$PHP_BIN" "$APP/artisan" package:discover --quiet
"$PHP_BIN" -r '
    $manifest = require $argv[1];
    $expected = [
        "webx-ui/admin" => "WebxUi\\Admin\\AdminServiceProvider",
        "webx-ui/mcp" => "WebxUi\\Mcp\\McpServiceProvider",
        "webx-ui/module-auth" => "WebxUi\\Auth\\AuthServiceProvider",
    ];
    foreach ($expected as $package => $provider) {
        if (! in_array($provider, $manifest[$package]["providers"] ?? [], true)) {
            fwrite(STDERR, "{$package} did not offer {$provider} to discovery\n");
            exit(1);
        }
        echo "   {$provider} discovered\n";
    }
' "$APP/bootstrap/cache/packages.php" || fail 'package discovery did not find the providers'

# Laravel reads .env immutably: the first assignment of a name wins, so appending to the file
# changes nothing. Values have to replace the ones the skeleton shipped with.
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

step "Migrate on $DB_CONNECTION"
set_env DB_CONNECTION "$DB_CONNECTION"
set_env APP_URL "$BASE"
set_env SESSION_DRIVER database

if [ "$DB_CONNECTION" != "sqlite" ]; then
    set_env DB_HOST "${DB_HOST:-127.0.0.1}"
    set_env DB_PORT "${DB_PORT:-3306}"
    set_env DB_DATABASE "${DB_DATABASE:-webx}"
    set_env DB_USERNAME "${DB_USERNAME:-root}"
    set_env DB_PASSWORD "${DB_PASSWORD:-}"
fi

note "$(grep -E '^DB_CONNECTION=|^DB_DATABASE=' "$APP/.env" | tr '\n' ' ')"

[ "$DB_CONNECTION" = "sqlite" ] && : > "$APP/database/database.sqlite"

"$PHP_BIN" "$APP/artisan" migrate --force --no-interaction
note 'migrations ran'

step "Create an administrator"
WEBX_ADMIN_PASSWORD="$ADMIN_PASSWORD" "$PHP_BIN" "$APP/artisan" webx:admin \
    --name=Smoke --email="$ADMIN_EMAIL" --no-interaction
note "$ADMIN_EMAIL created"

step "The modules answer to artisan"
"$PHP_BIN" "$APP/artisan" webx:mcp-tools | grep -q 'users_grant_role' \
    || fail 'the auth module offers no MCP tools'
note 'webx:mcp-tools lists the auth tools'

# --------------------------------------------------------------------------------------------

# Read the token out of the cookie jar. Signing in regenerates the session — session fixation
# is exactly what that is for — so the token has to be read again after it, not reused.
xsrf_token() {
    curl -s -o /dev/null -c "$COOKIES" -b "$COOKIES" "$BASE/sanctum/csrf-cookie"

    "$PHP_BIN" -r '
        foreach (explode("\n", (string) file_get_contents($argv[1])) as $line) {
            $parts = preg_split("/\t/", trim($line));
            if (($parts[5] ?? "") === "XSRF-TOKEN") {
                echo urldecode($parts[6]);
            }
        }
    ' "$COOKIES" | tail -c 512
}

sign_in_attempt() {
    local password="$1" token
    token="$(xsrf_token)"

    [ -n "$token" ] || fail 'no XSRF-TOKEN cookie — /sanctum/csrf-cookie did not answer'

    curl -s -o /dev/null -w '%{http_code}' -c "$COOKIES" -b "$COOKIES" \
        -H 'Accept: application/json' \
        -H "X-XSRF-TOKEN: $token" \
        -d "email=$ADMIN_EMAIL" -d "password=$password" \
        "$BASE/api/cms/auth/login"
}

run_http_checks() {
    local phase="$1"

    : > "$COOKIES"

    expect 200 "$(status "$BASE/cms")" "[$phase] the shell is public"
    expect 401 "$(status "$BASE/api/cms/manifest")" "[$phase] the manifest is closed to a stranger"

    # The real sign-in dance: the panel's API runs through the `web` group, so a POST needs a
    # CSRF token. Tests never see this — Laravel switches the check off while running them.
    expect 422 "$(sign_in_attempt 'not-the-password')" "[$phase] a wrong password is refused"
    expect 401 "$(status "$BASE/api/cms/manifest")" "[$phase] and leaves the panel closed"

    expect 200 "$(sign_in_attempt "$ADMIN_PASSWORD")" "[$phase] sign in"
    expect 200 "$(status "$BASE/api/cms/manifest")" "[$phase] the manifest opens for an administrator"
    expect 200 "$(status "$BASE/api/cms/auth/me")" "[$phase] me answers"

    expect 204 "$(
        curl -s -o /dev/null -w '%{http_code}' -c "$COOKIES" -b "$COOKIES" \
            -H 'Accept: application/json' -H "X-XSRF-TOKEN: $(xsrf_token)" -X POST \
            "$BASE/api/cms/auth/logout"
    )" "[$phase] sign out"

    expect 401 "$(status "$BASE/api/cms/manifest")" "[$phase] and the panel is closed again"
}

serve() {
    "$PHP_BIN" "$APP/artisan" serve --host=127.0.0.1 --port="$PORT" > "$WORKDIR/serve.log" 2>&1 &
    SERVER_PID=$!

    for _ in $(seq 1 40); do
        if curl -s -o /dev/null "$BASE/cms"; then
            return
        fi
        sleep 0.5
    done

    cat "$WORKDIR/serve.log" >&2
    fail 'the application never came up'
}

step "Serve it"
serve
note "listening on $BASE"

step "Check the panel"
run_http_checks 'plain'

step "Turn on the caches a deploy turns on"
cleanup
SERVER_PID=""
"$PHP_BIN" "$APP/artisan" config:cache --quiet
"$PHP_BIN" "$APP/artisan" route:cache --quiet
note 'config:cache and route:cache'
serve

step "Check the panel again"
# The one that matters: the auth provider closes the panel by rewriting another package's
# config during register(). If that does not survive being cached, the panel is open in
# production and nowhere else.
run_http_checks 'cached'

printf '\n\033[32m== The panel installs, migrates, signs in and stays closed to strangers.\033[0m\n'
