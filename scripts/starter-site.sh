#!/usr/bin/env bash
#
# Tear down the starter site and build it again from nothing (spec WEBX_UI_THEMES.md §18.2).
#
# The starter site is what everybody who runs `webx:setup` today gets: the skeleton, the default
# set of modules of a company site, three languages and the demo — and nothing done by hand. So
# it is never patched, only rebuilt; anything that had to be fixed by hand afterwards belongs in
# `webx:setup`, the theme or the skeleton. Run it after every change to any of the three.
#
# It is wired to this checkout the way the other demo sites are: Composer through a path
# repository (vendor/webx-ui/* are links into php/packages), npm through `file:` specifiers.
# The npm packages are taken as built — run `pnpm build` here first when they changed.
#
#   scripts/starter-site.sh            rebuild
#   scripts/starter-site.sh --down     tear down only: the directory (but the host config) and the database
#
# The defaults are an OSPanel machine; every one of them can be overridden:
#
#   STARTER_PATH      ../webx-starter.local, beside this checkout
#   STARTER_URL       http://<directory name>
#   STARTER_ADMIN     the email of the first administrator; default: git config user.email
#   PHP_BIN           php, or OSPanel's PHP-8.4 when it is there
#   COMPOSER_PHAR     composer.phar; default: the `composer` on the PATH
#   DB_HOST DB_PORT DB_USERNAME DB_PASSWORD DB_DATABASE    OSPanel's MariaDB, webx_starter
#   WEBX_ADMIN_PASSWORD  the administrator's password; without it setup makes one and prints it
#
# Any of them can live in .env.starter at the root of this checkout (ignored by git), one
# NAME=value per line; STARTER_ENV names another file.

set -euo pipefail

MONOREPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# This machine's answers, kept out of git (.env.* is ignored) and out of the site, which the
# rebuild deletes: any variable below, and WEBX_ADMIN_PASSWORD, which `webx:setup` takes instead
# of making up a new password every time.
STARTER_ENV="${STARTER_ENV:-$MONOREPO/.env.starter}"
if [ -f "$STARTER_ENV" ]; then
    set -a
    # shellcheck source=/dev/null
    . "$STARTER_ENV"
    set +a
fi

SITE="${STARTER_PATH:-$(cd "$MONOREPO/.." && pwd)/webx-starter.local}"
NAME="$(basename "$SITE")"
URL="${STARTER_URL:-http://$NAME}"
MODE="${1:-build}"

OSPANEL_PHP=/c/Work/OSPanel/modules/PHP-8.4/php.exe
if [ -z "${PHP_BIN:-}" ]; then
    if [ -x "$OSPANEL_PHP" ]; then PHP_BIN="$OSPANEL_PHP"; else PHP_BIN=php; fi
fi

DB_HOST="${DB_HOST:-MariaDB-11.8.local}"
DB_PORT="${DB_PORT:-3306}"
DB_USERNAME="${DB_USERNAME:-root}"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_DATABASE="${DB_DATABASE:-webx_starter}"

ADMIN="${STARTER_ADMIN:-$(git -C "$MONOREPO" config user.email || true)}"

step() { printf '\n\033[1m== %s\033[0m\n' "$1"; }
note() { printf '   %s\n' "$1"; }
fail() { printf '\n\033[31mFAILED: %s\033[0m\n' "$1" >&2; exit 1; }

on_windows() { [ "${OS:-}" = "Windows_NT" ]; }

# A path every program understands: C:/x/y on Windows, the path itself elsewhere. Git Bash
# rewrites a /c/... argument for the programs it starts, but never one inside a JSON string.
native() { if on_windows; then cygpath -m "$1"; else printf '%s' "$1"; fi; }

# Composer, under the name `webx:setup` and its children look for, whatever it is here.
SHIM="$(mktemp -d)"
trap 'rm -rf "$SHIM"' EXIT

if [ -n "${COMPOSER_PHAR:-}" ]; then
    [ -f "$COMPOSER_PHAR" ] || fail "COMPOSER_PHAR=$COMPOSER_PHAR is not a file."
    printf '#!/usr/bin/env bash\nexec "%s" "%s" "$@"\n' "$PHP_BIN" "$COMPOSER_PHAR" > "$SHIM/composer"
    chmod +x "$SHIM/composer"
    if on_windows; then
        printf '@"%s" "%s" %%*\r\n' "$(cygpath -w "$PHP_BIN")" "$(cygpath -w "$COMPOSER_PHAR")" > "$SHIM/composer.bat"
    fi
elif ! command -v composer > /dev/null; then
    fail 'No composer on the PATH — set COMPOSER_PHAR to a composer.phar.'
fi
export PATH="$SHIM:$(dirname "$PHP_BIN"):$PATH"

composer_bin() {
    if [ -n "${COMPOSER_PHAR:-}" ]; then "$PHP_BIN" "$COMPOSER_PHAR" "$@"; else composer "$@"; fi
}

artisan() { ( cd "$SITE" && "$PHP_BIN" artisan "$@" ); }

# Remove a link without following it. `rm -rf` through a link into this checkout deletes the
# sources (CLAUDE.md §4), and on Windows the links Composer and npm make are junctions, which
# only `rmdir` is sure to take away on their own.
unlink_one() {
    if on_windows; then cmd //c rmdir "$(cygpath -w "$1")" > /dev/null 2>&1 || rm -f "$1"; else rm -f "$1"; fi
}

database() {
    "$PHP_BIN" -r '
        [, $host, $port, $user, $password, $name, $action] = $argv;
        $pdo = new PDO("mysql:host={$host};port={$port}", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("DROP DATABASE IF EXISTS `{$name}`");
        if ($action === "create") {
            $pdo->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
    ' "$DB_HOST" "$DB_PORT" "$DB_USERNAME" "$DB_PASSWORD" "$DB_DATABASE" "$1"
}

# The host's own config (OSPanel keeps it in .osp/) is not the site's: it survives the rebuild,
# so the address goes on answering from the same directory.
HOST_CONFIG="$(mktemp -d)"

teardown() {
    step "Tear down $SITE"

    if [ -d "$SITE" ]; then
        [ -d "$SITE/.osp" ] && cp -a "$SITE/.osp" "$HOST_CONFIG/"

        local canary="$MONOREPO/php/packages/themes/composer.json"
        local links
        # Every link, wherever it points: vendor/webx-ui/* and node_modules/@webx-ui/* into this
        # checkout, public/storage into the site itself. Taking a link away never touches its target.
        links="$(find "$SITE" -type l 2>/dev/null || true)"
        while IFS= read -r link; do
            [ -n "$link" ] && unlink_one "$link"
        done <<< "$links"

        # Anything still pointing out of the directory stops the removal rather than going with it.
        if [ -n "$(find "$SITE" -type l 2>/dev/null | head -1)" ]; then
            find "$SITE" -type l >&2
            fail 'links are left in the site; remove them by hand (rmdir on Windows), then run this again.'
        fi

        rm -rf "$SITE"
        [ -f "$canary" ] || fail "the removal reached this checkout: $canary is gone. Restore it with git."
        note "directory removed, $(echo "$links" | grep -c . || true) links taken out first"
    else
        note 'no directory'
    fi

    database drop
    note "database $DB_DATABASE dropped on $DB_HOST"
}

teardown

if [ "$MODE" = "--down" ]; then
    if [ -d "$HOST_CONFIG/.osp" ]; then
        mkdir -p "$SITE" && cp -a "$HOST_CONFIG/.osp" "$SITE/"
        note 'the host config is kept in an otherwise empty directory'
    fi
    rm -rf "$HOST_CONFIG"
    printf '\n\033[32m== Torn down.\033[0m\n'
    exit 0
fi

VERSION="$(node -p "require(process.argv[1]).version" "$(native "$MONOREPO/php/package.json")")"

step "Create the site from the skeleton (webx-ui/site $VERSION, this checkout)"
SKELETON_REPOSITORY="$(node -e '
    const [url, version] = process.argv.slice(1)
    process.stdout.write(JSON.stringify({ type: "path", url, options: { symlink: false, versions: { "webx-ui/site": version } } }))
' "$(native "$MONOREPO/php/site")" "$VERSION")"
(
    cd "$(dirname "$SITE")"
    composer_bin create-project webx-ui/site "$NAME" "$VERSION" --repository="$SKELETON_REPOSITORY" \
        --no-install --no-scripts --no-interaction
)

if [ -d "$HOST_CONFIG/.osp" ]; then
    cp -a "$HOST_CONFIG/.osp" "$SITE/"
    note 'host config put back'
fi
rm -rf "$HOST_CONFIG"

step 'Point Composer at this checkout'
# What `scripts/packages.mjs local` does on the other demo sites: the registry for everything but
# webx-ui/*, and the packages of this checkout under the version the monorepo gives them.
node -e '
    const { readFileSync, writeFileSync } = require("node:fs")
    const [site, monorepo] = process.argv.slice(1)
    const own = JSON.parse(readFileSync(monorepo + "/php/composer.json", "utf8"))
    const versions = { ...own.repositories.find((repository) => repository.type === "path").options.versions }
    delete versions["//"]
    const file = site + "/composer.json"
    const composer = JSON.parse(readFileSync(file, "utf8"))
    composer.repositories = [
        { name: "packagist.org", type: "composer", url: "https://repo.packagist.org", exclude: ["webx-ui/*"] },
        { type: "path", url: monorepo + "/php/packages/*", options: { versions } },
    ]
    writeFileSync(file, JSON.stringify(composer, null, 4) + "\n")
' "$(native "$SITE")" "$(native "$MONOREPO")"
( cd "$SITE" && composer_bin install --no-interaction )

step 'Environment'
cp "$SITE/.env.example" "$SITE/.env"
artisan key:generate --no-interaction

step "Database $DB_DATABASE on $DB_HOST"
database create
note 'created empty'

step 'webx:setup'
setup=(
    webx:setup --no-interaction
    --name='WebX Starter' --domain="$NAME" --locales=en,ru,pl
    --db-connection=mariadb --db-host="$DB_HOST" --db-port="$DB_PORT"
    --db-username="$DB_USERNAME" --db-password="$DB_PASSWORD" --db="$DB_DATABASE"
    --admin-name=Owner --demo --no-build
)
[ -n "$ADMIN" ] && setup+=(--admin="$ADMIN")
[ -n "${COMPOSER_PHAR:-}" ] && setup+=(--composer="$(native "$COMPOSER_PHAR")")
artisan "${setup[@]}"

step 'Point npm at this checkout and build'
[ -d "$MONOREPO/packages/core/dist" ] || fail "packages/core/dist is missing — run pnpm build in $MONOREPO first."
# Setup writes registry versions; every @webx-ui/* becomes the package in this checkout, relative
# so that npm links it and a rebuild here only needs `npm run build` there.
node -e '
    const { readFileSync, writeFileSync, existsSync } = require("node:fs")
    const { relative, sep } = require("node:path")
    const [site, monorepo] = process.argv.slice(1)
    const file = site + "/package.json"
    const pkg = JSON.parse(readFileSync(file, "utf8"))
    const base = relative(site, monorepo).split(sep).join("/")
    for (const name of Object.keys(pkg.dependencies ?? {})) {
        if (!name.startsWith("@webx-ui/")) continue
        const directory = name.slice("@webx-ui/".length)
        if (!existsSync(monorepo + "/packages/" + directory)) throw new Error(name + " has no packages/" + directory + " here")
        pkg.dependencies[name] = "file:" + base + "/packages/" + directory
    }
    writeFileSync(file, JSON.stringify(pkg, null, 4) + "\n")
' "$(native "$SITE")" "$(native "$MONOREPO")"
( cd "$SITE" && npm install --no-audit --no-fund && npm run build )

step 'Check'
artisan webx:doctor
artisan test

home="$(mktemp)"
code="$(curl -s -o "$home" -w '%{http_code}' "$URL/" || true)"
if [ "$code" = "000" ]; then
    note "$URL does not answer — register the directory with the web server, then open it."
else
    [ "$code" = "200" ] || fail "GET $URL/ answered $code"
    grep -q '<style data-webx-theme' "$home" || fail 'the home page has no theme tokens (<style data-webx-theme>)'
    grep -q 'themes/webx-ui/theme-default/[^"]*/theme.css' "$home" || fail 'the home page does not link theme-default'
    note "GET $URL/ — 200, styled by theme-default"
    for path in /ru /pl /cms /kitchen-sink; do
        code="$(curl -s -o /dev/null -w '%{http_code}' "$URL$path")"
        [ "$code" = "200" ] || fail "GET $URL$path answered $code"
        note "GET $URL$path — 200"
    done
fi
rm -f "$home"

if [ -n "${WEBX_ADMIN_PASSWORD:-}" ]; then
    printf '\n\033[32m== %s is a new site again. The administrator password is the one in %s.\033[0m\n' "$URL" "$STARTER_ENV"
else
    printf '\n\033[32m== %s is a new site again. The administrator password is the one setup printed above.\033[0m\n' "$URL"
fi
