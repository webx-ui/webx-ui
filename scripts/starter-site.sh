#!/usr/bin/env bash
#
# Tear down the starter site and build it again from nothing (spec WEBX_UI_THEMES.md §18.2).
#
# The starter site is what everybody who runs `webx:setup` today gets: the skeleton, the default
# set of modules of a company site, three languages and the demo — and nothing done by hand but
# the three commands setup prints for a dev copy (the block types and the demo, which setup never
# writes: BLOCK_SCHEMAS §1). So it is never patched, only rebuilt; anything that had to be fixed
# by hand afterwards belongs in `webx:setup`, the theme or the skeleton. Run it after every change
# to any of the three.
#
# It is wired to this checkout the way the other demo sites are: Composer through a path
# repository (vendor/webx-ui/* are links into php/packages), npm through `file:` specifiers.
# The npm packages are taken as built — run `pnpm build` here first when they changed.
#
#   scripts/starter-site.sh                        rebuild
#   scripts/starter-site.sh --with=blog,services   rebuild with these modules on top of the company set
#                                                  (the showcase: --with=blog,services,events,vacancies,press,recipes)
#   scripts/starter-site.sh --down                 tear down only: the directory (but the host config) and the database
#
# The defaults are an OSPanel machine; every one of them can be overridden:
#
#   STARTER_PATH      ../webx-starter-test.local, beside this checkout — never a site you keep:
#                     a directory under git (webx-starter.local is a clone of the starter on the
#                     hosting) is refused, since the rebuild deletes it whole
#   STARTER_URL       http://<directory name>
#   STARTER_ADMIN     the email of the first administrator; default: git config user.email
#   STARTER_WITH      modules on top of the company set, as --with takes them (blog,services)
#   PHP_BIN           php, or OSPanel's PHP-8.4 when it is there
#   COMPOSER_PHAR     composer.phar; default: the `composer` on the PATH
#   DB_HOST DB_PORT DB_USERNAME DB_PASSWORD DB_DATABASE    OSPanel's MariaDB, webx_starter_test
#   WEBX_ADMIN_PASSWORD  the administrator's password; without it setup makes one and prints it
#
# Any of them can live in .env.starter at the root of this checkout (ignored by git), one
# NAME=value per line; STARTER_ENV names another file.

set -euo pipefail

MONOREPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# shellcheck source=lib/linked-site.sh
. "$MONOREPO/scripts/lib/linked-site.sh"

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

SITE="${STARTER_PATH:-$(cd "$MONOREPO/.." && pwd)/webx-starter-test.local}"
NAME="$(basename "$SITE")"
URL="${STARTER_URL:-http://$NAME}"
MODE=build
# The modules on top of the default set of a company site, with their demo: the blog, the
# services, events, vacancies, press and recipes have pages of their own that a theme dresses
# (THEMES §10, TH3.2–TH3.4), and a starter without them would never show those pages. The showcase
# is built with --with=blog,services,events,vacancies,press,recipes.
WITH="${STARTER_WITH:-}"
for argument in "$@"; do
    case "$argument" in
        --down) MODE=--down ;;
        --with=*) WITH="${argument#--with=}" ;;
        *) printf 'Unknown argument: %s\n' "$argument" >&2; exit 2 ;;
    esac
done

OSPANEL_PHP=/c/Work/OSPanel/modules/PHP-8.4/php.exe
if [ -z "${PHP_BIN:-}" ]; then
    if [ -x "$OSPANEL_PHP" ]; then PHP_BIN="$OSPANEL_PHP"; else PHP_BIN=php; fi
fi

DB_HOST="${DB_HOST:-MariaDB-11.8.local}"
DB_PORT="${DB_PORT:-3306}"
DB_USERNAME="${DB_USERNAME:-root}"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_DATABASE="${DB_DATABASE:-webx_starter_test}"

ADMIN="${STARTER_ADMIN:-$(git -C "$MONOREPO" config user.email || true)}"

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

artisan() { ( cd "$SITE" && "$PHP_BIN" artisan "$@" ); }

# The host's own config (OSPanel keeps it in .osp/) is not the site's: it survives the rebuild,
# so the address goes on answering from the same directory.
HOST_CONFIG="$(mktemp -d)"

teardown() {
    step "Tear down $SITE"

    # A site with its own history is somebody's work, not a build of this script: the clone of the
    # starter that deploys to the hosting lived at the old default path and went with the rebuild.
    if [ -e "$SITE/.git" ]; then
        fail "$SITE is a git checkout; this script only rebuilds a throwaway site. Point STARTER_PATH elsewhere."
    fi

    if [ -d "$SITE" ]; then
        [ -d "$SITE/.osp" ] && cp -a "$SITE/.osp" "$HOST_CONFIG/"

        remove_site_tree "$SITE"
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

VERSION="$(monorepo_version)"

step "Create the site from the skeleton (webx-ui/site $VERSION, this checkout)"
create_skeleton "$SITE"

if [ -d "$HOST_CONFIG/.osp" ]; then
    cp -a "$HOST_CONFIG/.osp" "$SITE/"
    note 'host config put back'
fi
rm -rf "$HOST_CONFIG"

# What `webx:theme:sync` publishes and Vite builds is named by its content and cached forever
# (THEMES §13.2): the skeleton says so in docker/nginx.conf and public/.htaccess, but OSPanel's
# nginx reads neither — its server block is the host's, in .osp/. Said there once, kept by the
# rebuild like the rest of .osp/; the browser checks of the showcase measure the header.
OSP_NGINX="$SITE/.osp/nginx/$NAME.conf"
if [ -f "$OSP_NGINX" ] && ! grep -q 'location /themes/' "$OSP_NGINX"; then
    cat >> "$OSP_NGINX" <<'NGINX'

# scripts/starter-site.sh: named by their content, cached forever — as the skeleton's docker/nginx.conf.
location /build/ {
  add_header Cache-Control "public, max-age=31536000, immutable";
  try_files $uri =404;
}

location /themes/ {
  add_header Cache-Control "public, max-age=31536000, immutable";
  try_files $uri =404;
}
NGINX
    OSP_BAT="$(dirname "$(dirname "$SITE")")/bin/osp.bat"
    if [ -f "$OSP_BAT" ]; then
        cmd //c "$(native "$OSP_BAT")" restart nginx > /dev/null 2>&1 || true
        note "/build/ and /themes/ cached forever in $OSP_NGINX; nginx restarted"
    else
        note "/build/ and /themes/ cached forever in $OSP_NGINX; restart nginx to apply"
    fi
fi

step 'Point Composer at this checkout'
point_composer "$SITE"
( cd "$SITE" && composer_bin install --no-interaction )

step 'Environment'
cp "$SITE/.env.example" "$SITE/.env"
# The page FAQ and the interlinking of module-seo are off on a new site — a project's brief turns
# them on. The starter shows everything a theme dresses, so it turns them on, before setup boots:
# Kitchen sink › Modules › FAQ and links is seeded with both (THEMES §23, TH3.4).
printf '\nWEBX_SEO_FAQ=true\nWEBX_SEO_LINKS=true\n' >> "$SITE/.env"
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
    --admin-name=Owner --no-build
)
[ -n "$WITH" ] && setup+=(--modules="+$WITH")
[ -n "$ADMIN" ] && setup+=(--admin="$ADMIN")
[ -n "${COMPOSER_PHAR:-}" ] && setup+=(--composer="$(native "$COMPOSER_PHAR")")
artisan "${setup[@]}"

step 'Block types and the demo'
# What setup prints for a dev copy, in its order: the theme's types, the offers of the modules on
# the site, then the demo on top of both. The tests under tests/starter stand on all three.
artisan webx:blocks:import --theme --publish --no-interaction
artisan webx:blocks:offered --install --no-interaction
artisan webx:demo --no-interaction

step 'Point npm at this checkout and build'
point_npm "$SITE"
( cd "$SITE" && npm install --no-audit --no-fund && npm run build )

step 'Check'
artisan webx:doctor
# CSS knows only tokens (THEMES §8): every layer of the chain, the modules and the widgets.
artisan webx:theme:check || fail 'webx:theme:check found what is listed above'
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
    grep -q 'themes/webx-ui/widgets/[^"]*/runtime.css' "$home" || fail 'the home page does not link the widgets runtime stylesheet'
    grep -qE '<script type="module" src="[^"]*/themes/webx-ui/widgets/[a-f0-9]{12}/runtime\.js"></script>' "$home" \
        || fail 'the home page does not load the widgets runtime'
    ! grep -q '<!--webx-widgets-->' "$home" || fail 'the widgets marker was left on the page'
    note "GET $URL/ — the widgets runtime is loaded"
    paths=(/ru /pl /cms /kitchen-sink)
    # A module asked for on top answers at its prefix; press's /press is the page its demo seeds.
    case ",$WITH," in *,blog,*) paths+=(/blog) ;; esac
    case ",$WITH," in *,services,*) paths+=(/services) ;; esac
    case ",$WITH," in *,events,*) paths+=(/events) ;; esac
    case ",$WITH," in *,vacancies,*) paths+=(/careers) ;; esac
    case ",$WITH," in *,press,*) paths+=(/press) ;; esac
    case ",$WITH," in *,recipes,*) paths+=(/recipes) ;; esac
    for path in "${paths[@]}"; do
        code="$(curl -s -o /dev/null -w '%{http_code}' "$URL$path")"
        [ "$code" = "200" ] || fail "GET $URL$path answered $code"
        note "GET $URL$path — 200"
    done
    # The theme's layout prints the page's FAQ and links; the showcase page has both.
    faq="$(curl -s "$URL/kitchen-sink/modules/faq-and-links")"
    grep -q 'class="webx-faq"' <<< "$faq" || fail 'Kitchen sink › Modules › FAQ and links prints no FAQ'
    grep -q 'class="webx-links"' <<< "$faq" || fail 'Kitchen sink › Modules › FAQ and links prints no links'
    note "GET $URL/kitchen-sink/modules/faq-and-links — the FAQ and the links under the page"
fi
rm -f "$home"

if [ -n "${WEBX_ADMIN_PASSWORD:-}" ]; then
    printf '\n\033[32m== %s is a new site again. The administrator password is the one in %s.\033[0m\n' "$URL" "$STARTER_ENV"
else
    printf '\n\033[32m== %s is a new site again. The administrator password is the one setup printed above.\033[0m\n' "$URL"
fi
