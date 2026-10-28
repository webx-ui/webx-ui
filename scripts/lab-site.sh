#!/usr/bin/env bash
#
# The lab: a permanent site in devbox wired to this checkout, so that what the day's branch
# changes in the packages is seen on a real site before the release (docs/pitfalls/packages-and-demo-sites.md,
# «Lab-сайт»). Every module of the catalogue, theme-default and the demo of every module; no git,
# no hosting, no theme of its own. The database is disposable: `reset` builds it again.
#
#   scripts/lab-site.sh create     build what is missing (idempotent), then refresh
#   scripts/lab-site.sh refresh    after changes here: new modules, Composer, npm builds, migrations,
#                                  the panel, the theme, caches, restart, doctor
#   scripts/lab-site.sh reset      drop the lab's database and directory, then create
#   scripts/lab-site.sh status     which commit of this checkout the lab was refreshed at, and whether it answers
#
# Composer reaches this checkout through a path repository (vendor/webx-ui/* are links into
# php/packages), npm through `file:` specifiers (node_modules/@webx-ui/* are links into packages).
# The site runs in devbox under the `site` CLI (template webx); this script talks to it as its owner.
#
#   LAB_NAME         webx-lab — the directory beside this checkout, the subdomain, the database
#   LAB_ADMIN        admin@example.com; the password is made once and kept in <site>/.lab-admin

set -euo pipefail

MONOREPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
# shellcheck source=lib/linked-site.sh
. "$MONOREPO/scripts/lib/linked-site.sh"

NAME="${LAB_NAME:-webx-lab}"
SITE="$(dirname "$MONOREPO")/$NAME"
ID="${NAME//-/_}"
ADMIN="${LAB_ADMIN:-admin@example.com}"
DEV_DOMAIN="${DEV_DOMAIN:-$(sed -n 's/^DEV_DOMAIN=//p' /opt/devbox/.env 2> /dev/null || true)}"
[ -n "$DEV_DOMAIN" ] || fail 'DEV_DOMAIN is not set and /opt/devbox/.env does not say it — this script is for devbox.'
URL="https://$NAME.$DEV_DOMAIN"
STATE="$SITE/.lab-state"
SECRET="$SITE/.lab-admin"
# Written once setup, the block types and the demo are through: a create that stopped halfway
# runs them again, one that finished goes straight to refresh.
BUILT="$SITE/.lab-built"

artisan() { ( cd "$SITE" && "$PHP_BIN" artisan "$@" ); }

env_get() { [ -f "$SITE/.env" ] && sed -n "s/^$1=//p" "$SITE/.env" | tail -n1 | sed -e 's/^"\(.*\)"$/\1/' || true; }

env_set() {
    local tmp
    tmp="$(mktemp)"
    K="$1" V="$2" awk 'BEGIN { k = ENVIRON["K"]; v = ENVIRON["V"] }
        index($0, k "=") == 1 && !done { print k "=" v; done = 1; next } { print }
        END { if (!done) print k "=" v }' "$SITE/.env" > "$tmp"
    cat "$tmp" > "$SITE/.env"
    rm -f "$tmp"
}

# The lab follows the main checkout: pnpm run from a worktree takes away the node_modules of
# the main one (CLAUDE.md §4), and a lab on a worktree goes when the worktree does.
if [ "$(git -C "$MONOREPO" rev-parse --git-dir)" != "$(git -C "$MONOREPO" rev-parse --git-common-dir)" ]; then
    fail "$MONOREPO is a worktree; run this from the main checkout."
fi

guard_site() {
    [ "$SITE" != "$MONOREPO" ] && [ "$(basename "$SITE")" = "$NAME" ] || fail "refusing to touch $SITE"
    if [ -e "$SITE/.git" ]; then
        fail "$SITE is a git checkout; the lab has no history of its own. Move it away or set LAB_NAME."
    fi
}

# Make sure the `site` CLI knows the lab. It creates the database and a user with rights on
# that database only, writes them to .env and starts the containers.
register_site() {
    step "devbox site $NAME"
    site create "$NAME" webx
}

lab_environment() {
    # The shared Redis and Manticore of devbox are told apart by prefix alone: with the skeleton's
    # default (the slug of APP_NAME) two sites of the same name read each other's cache.
    env_set CACHE_STORE redis
    env_set REDIS_HOST redis
    env_set REDIS_PASSWORD null
    env_set REDIS_PREFIX "${ID}_"
    env_set CACHE_PREFIX "${ID}_cache_"
    # The template says these to the containers; artisan here reads .env.
    env_set MANTICORE_HOST manticore
    env_set MANTICORE_PORT 9308
    env_set MANTICORE_TABLE_PREFIX "$ID"
    env_set MAIL_MAILER log
    env_set APP_URL "$URL"
    # The demo catalogue sells through the cart: without a currency the cart refuses every write.
    [ -n "$(env_get WEBX_CATALOG_CURRENCY)" ] || env_set WEBX_CATALOG_CURRENCY EUR
    [ -n "$(env_get APP_KEY)" ] || artisan key:generate --force --no-interaction
}

admin_password() {
    if [ -f "$SECRET" ]; then
        sed -n 2p "$SECRET"
    else
        local password
        password="$(od -An -tx1 -N12 /dev/urandom | tr -d ' \n')"
        ( umask 077 && printf '%s\n%s\n' "$ADMIN" "$password" > "$SECRET" )
        chmod 600 "$SECRET"
        printf '%s' "$password"
    fi
}

build() {
    guard_site

    if [ ! -f "$SITE/artisan" ]; then
        # Only the admin password kept by reset may wait there: create-project wants no directory.
        local kept=""
        if [ -e "$SITE" ]; then
            [ -z "$(ls -A "$SITE" | grep -vx .lab-admin)" ] || fail "$SITE is there but is not a site; reset takes it away."
            [ -f "$SECRET" ] && kept="$(cat "$SECRET")"
            rm -f "$SECRET"
            rmdir "$SITE"
        fi
        step "Create the site from the skeleton (webx-ui/site $(monorepo_version), this checkout)"
        create_skeleton "$SITE"
        if [ -n "$kept" ]; then
            ( umask 077 && printf "%s\n" "$kept" > "$SECRET" )
            chmod 600 "$SECRET"
        fi
        point_composer "$SITE"
        ( cd "$SITE" && composer_bin install --no-interaction )
    else
        note "$SITE is there"
    fi

    register_site
    lab_environment

    if [ -f "$BUILT" ]; then
        note 'setup, block types and demo were done before'
        return
    fi

    step 'webx:setup — every module of the catalogue, theme-default'
    local password
    password="$(admin_password)"
    WEBX_ADMIN_PASSWORD="$password" artisan webx:setup --no-interaction \
        --name='WebX Lab' --domain="$URL" --locales=en,ru,pl --modules=all \
        --db-connection=mariadb --db-host="$(env_get DB_HOST)" --db-port="$(env_get DB_PORT)" \
        --db-username="$(env_get DB_USERNAME)" --db-password="$(env_get DB_PASSWORD)" --db="$(env_get DB_DATABASE)" \
        --admin="$ADMIN" --admin-name='Lab admin' --no-build
    # Setup writes the address it was given; what it may rewrite of ours goes back.
    lab_environment

    step 'Block types and the demo of every module'
    artisan webx:blocks:import --theme --publish --no-interaction
    artisan webx:blocks:offered --install --no-interaction
    artisan webx:demo --no-interaction
    date -u +%FT%TZ > "$BUILT"
}

# npm packages of this checkout whose sources are newer than their dist (or that have none).
stale_packages() {
    local directory name newest built
    for directory in "$MONOREPO"/packages/*/; do
        directory="${directory%/}"
        [ -f "$directory/package.json" ] || continue
        grep -q '"build"[[:space:]]*:' "$directory/package.json" || continue
        name="$(node -p "require(process.argv[1]).name" "$directory/package.json")"
        if [ ! -d "$directory/dist" ]; then
            printf '%s\n' "$name"
            continue
        fi
        built="$(find "$directory/dist" -type f -printf '%T@\n' | sort -n | tail -1)"
        newest="$(find "$directory" \( -path "$directory/node_modules" -o -path "$directory/dist" \) -prune \
            -o -type f -printf '%T@\n' | sort -n | tail -1)"
        if [ -z "$built" ] || awk -v a="$newest" -v b="$built" 'BEGIN { exit !(a > b) }'; then
            printf '%s\n' "$name"
        fi
    done
}

refresh() {
    guard_site
    [ -f "$BUILT" ] || fail "the lab is not built yet — scripts/lab-site.sh create"
    local started=$SECONDS done=()

    local branch head
    branch="$(git -C "$MONOREPO" rev-parse --abbrev-ref HEAD)"
    head="$(git -C "$MONOREPO" rev-parse --short HEAD)"

    step "npm packages of this checkout ($branch @ $head)"
    local stale
    stale="$(stale_packages)"
    if [ -n "$stale" ]; then
        local filters=()
        while IFS= read -r name; do filters+=(--filter "$name"); done <<< "$stale"
        ( cd "$MONOREPO" && pnpm "${filters[@]}" build )
        done+=("built $(echo "$stale" | tr '\n' ' ')")
    else
        note 'every dist is newer than its sources'
    fi

    step 'Modules the catalogue has and the lab does not'
    local missing
    missing="$(artisan webx:modules --json | node -e '
        let input = ""
        process.stdin.on("data", (chunk) => (input += chunk)).on("end", () => {
            const { modules } = JSON.parse(input)
            process.stdout.write(modules.filter((module) => !module.installed).map((module) => module.id + " " + module.package).join("\n"))
        })
    ')"
    point_composer "$SITE"
    if [ -n "$missing" ]; then
        # Two steps, not `composer require`: a partial update holds the neighbours to the lock
        # (docs/pitfalls/packages-and-demo-sites.md).
        local packages=()
        while read -r _ package; do packages+=("$package:*"); done <<< "$missing"
        ( cd "$SITE" && composer_bin require "${packages[@]}" --no-update --no-interaction )
        done+=("installed $(echo "$missing" | cut -d' ' -f1 | tr '\n' ' ')")
    else
        note 'none'
    fi

    step 'Composer: webx-ui/* from this checkout'
    # The registry is excluded for webx-ui/* (point_composer), so nothing of ours is downloaded;
    # the update follows a version raised here and a requirement a package gained.
    ( cd "$SITE" && composer_bin update 'webx-ui/*' --no-interaction )

    step 'Migrations'
    artisan migrate --force --no-interaction

    if [ -n "$missing" ]; then
        step 'Block types and demo of the new modules'
        artisan webx:blocks:offered --install --no-interaction
        # Every module rather than the new ones by name: a module without demo content (the
        # cart) is refused by `--module`, and what the journal holds is skipped anyway.
        artisan webx:demo --no-interaction
    fi

    step 'Panel and the site build'
    # The lab keeps no entry of its own, so it is written over: `--sync` alone never adds a module
    # to an existing admin.ts (docs/pitfalls/packages-and-demo-sites.md).
    artisan webx:panel --sync --force --no-interaction
    point_npm "$SITE"
    ( cd "$SITE" && npm install --no-audit --no-fund && npm run build )

    step 'Theme, caches, restart'
    artisan webx:theme:sync --no-interaction
    artisan optimize:clear --no-interaction
    artisan cache:clear --no-interaction
    artisan queue:restart --no-interaction
    site restart "$NAME"

    step 'Check'
    artisan webx:doctor || fail 'webx:doctor found what is listed above'
    local path code
    for path in / /cms; do
        code="000"
        for _ in $(seq 1 30); do
            code="$(curl -s -o /dev/null -w '%{http_code}' "$URL$path" || true)"
            [ "$code" = "000" ] || [ "$code" = "502" ] || break
            sleep 1
        done
        [ "$code" = "200" ] || fail "GET $URL$path answered $code"
        note "GET $URL$path — 200"
    done

    local version dirty
    version="$(monorepo_version)"
    dirty="$(git -C "$MONOREPO" status --porcelain | grep -c . || true)"
    printf 'BRANCH=%s\nHEAD=%s\nVERSION=%s\nDIRTY=%s\nAT=%s\n' \
        "$branch" "$(git -C "$MONOREPO" rev-parse HEAD)" "$version" "$dirty" "$(date -u +%FT%TZ)" > "$STATE"

    printf '\n\033[32m== %s refreshed in %ss: %s @ %s, version %s%s\033[0m\n' "$URL" "$((SECONDS - started))" \
        "$branch" "$head" "$version" "$([ "$dirty" = 0 ] || echo ", $dirty uncommitted files")"
    if [ ${#done[@]} -gt 0 ]; then printf '   %s\n' "${done[@]}"; fi
    note "panel $URL/cms — $ADMIN, the password is in $SECRET"
}

reset() {
    guard_site
    step "Reset $NAME"
    local kept=""
    if [ -f "$SECRET" ]; then kept="$(cat "$SECRET")"; fi

    if [ -f "$SITE/.env" ] && [ -n "$(env_get DB_DATABASE)" ]; then
        DB_HOST="$(env_get DB_HOST)" DB_PORT="$(env_get DB_PORT)" DB_USERNAME="$(env_get DB_USERNAME)" \
            DB_PASSWORD="$(env_get DB_PASSWORD)" DB_DATABASE="$(env_get DB_DATABASE)" database create
        note "database $(env_get DB_DATABASE) dropped and made again empty"
    fi

    if docker ps -q --filter "label=devbox.site=$NAME" | grep -q .; then
        site down "$NAME"
    fi

    if [ -d "$SITE" ]; then
        remove_site_tree "$SITE"
        [ -f "$MONOREPO/packages/core/package.json" ] || fail "the removal reached this checkout: packages/core is gone"
    fi

    if [ -n "$kept" ]; then
        mkdir -p "$SITE"
        ( umask 077 && printf '%s\n' "$kept" > "$SECRET" )
        chmod 600 "$SECRET"
        note 'the admin password is kept'
    fi

    build
    refresh
}

status() {
    printf '%s — %s\n' "$NAME" "$URL"
    if [ ! -f "$STATE" ]; then
        printf '   never refreshed%s\n' "$([ -d "$SITE" ] && echo '' || echo "; no directory $SITE — scripts/lab-site.sh create")"
    else
        # shellcheck source=/dev/null
        local BRANCH HEAD VERSION DIRTY AT
        eval "$(grep -E '^(BRANCH|HEAD|VERSION|DIRTY|AT)=' "$STATE")"
        local now behind
        now="$(git -C "$MONOREPO" rev-parse HEAD)"
        printf '   refreshed %s at %s @ %s (version %s, %s uncommitted files then)\n' "$AT" "$BRANCH" "${HEAD:0:8}" "$VERSION" "$DIRTY"
        if [ "$now" = "$HEAD" ]; then
            printf '   this checkout is at the same commit (%s)\n' "$(git -C "$MONOREPO" rev-parse --abbrev-ref HEAD)"
        elif git -C "$MONOREPO" merge-base --is-ancestor "$HEAD" "$now" 2> /dev/null; then
            behind="$(git -C "$MONOREPO" rev-list --count "$HEAD..$now")"
            printf '   \033[33mbehind by %s commits\033[0m — scripts/lab-site.sh refresh\n' "$behind"
        else
            printf '   \033[33mthis checkout moved to another line (%s @ %s)\033[0m — scripts/lab-site.sh refresh\n' \
                "$(git -C "$MONOREPO" rev-parse --abbrev-ref HEAD)" "${now:0:8}"
        fi
    fi
    local containers code
    containers="$(docker ps --filter "label=devbox.site=$NAME" --format '{{.Names}}' | wc -l)"
    code="$(curl -s -o /dev/null -w '%{http_code}' "$URL/" || true)"
    printf '   containers up: %s; GET / — %s\n' "$containers" "$code"
}

case "${1:-}" in
    # Not `build && refresh`: errexit is off on the left of &&, and a failing step would go unnoticed.
    create) build; refresh ;;
    refresh) refresh ;;
    reset) reset ;;
    status) status ;;
    *) sed -n '8,12p' "$0" | sed 's/^# \{0,1\}//' >&2; exit 2 ;;
esac
