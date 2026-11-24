#!/usr/bin/env bash
#
# The lab: a permanent dev site on the wxp platform wired to this checkout, so that what the day's
# branch changes in the packages is seen on a real site before the release
# (docs/pitfalls/packages-and-demo-sites.md, «Lab-сайт»). Every module of the catalogue,
# theme-default and the demo of every module; no git, no stage, no theme of its own. The database
# is disposable: `reset` builds it again.
#
#   scripts/lab-site.sh refresh    after changes here: npm builds, new modules, Composer, migrations,
#                                  the panel, the theme, caches, restart, doctor
#   scripts/lab-site.sh reset      dev of the lab made again empty (wxp dev reset), then setup, demo, refresh
#   scripts/lab-site.sh status     which commit the lab was refreshed at, what wxp says, whether it answers
#
# The platform runs the site (container wxp-webx-lab-dev, code in ~/work/webx-lab) in live mode:
# vendor/webx-ui/* are links into php/packages of this checkout, mounted read-only, and Composer is
# the core's (`wxp live sync`: install by composer.live.json, migrations, caches) — the container has
# none. artisan goes through `wxp exec`, npm through `wxp node`; nothing here calls docker.
#
#   LAB_NAME         webx-lab — the site of the platform, the directory beside this checkout
#   LAB_ADMIN        admin@example.com; the password is made once and kept in <site>/.lab-admin
#
# What the lab keeps of its own beside the password, through reset and every rewrite of .env:
#   <site>/.lab-env      lines of .env the skeleton has no say in (keys of services, switches);
#                        written over the template's by every refresh and after setup
#   <site>/.lab-config/  files of config/ the skeleton does not have (a carrier's own schedule);
#                        copied into config/ after setup, and by refresh when gone
# Neither is ever printed: names only, the values may be a client's keys.

set -euo pipefail

MONOREPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
# shellcheck source=lib/linked-site.sh
. "$MONOREPO/scripts/lib/linked-site.sh"

command -v wxp > /dev/null 2>&1 || fail 'нужен wxp-клиент (docs/workspace.md §4.6 платформы)'

NAME="${LAB_NAME:-webx-lab}"
SITE="$(dirname "$MONOREPO")/$NAME"
ID="${NAME//-/_}"
ADMIN="${LAB_ADMIN:-admin@example.com}"
STATE="$SITE/.lab-state"
SECRET="$SITE/.lab-admin"
LAB_ENV="$SITE/.lab-env"
LAB_CONFIG="$SITE/.lab-config"
# Written once setup, the block types and the demo are through: reset takes it away.
BUILT="$SITE/.lab-built"

# The address is the platform's: the host of dev as the core records it.
lab_url() {
    local host
    host="$(wxp --json status "$NAME" 2> /dev/null | node -e '
        let input = ""
        process.stdin.on("data", (chunk) => (input += chunk)).on("end", () => {
            try {
                const site = (JSON.parse(input).sites ?? []).find((s) => s.site === process.argv[1])
                process.stdout.write(site?.envs?.dev?.host ?? "")
            } catch {}
        })
    ' "$NAME" || true)"
    [ -n "$host" ] || [ -z "${DEV_DOMAIN:-}" ] || host="$NAME.$DEV_DOMAIN"
    [ -n "$host" ] || fail "wxp does not know the site $NAME and DEV_DOMAIN is not set"
    printf 'https://%s' "$host"
}

# Inside the dev container: the platform gives it the environment (database, Redis, APP_KEY).
lab() { wxp exec "$NAME" dev -- "$@"; }
artisan() { lab php artisan "$@"; }

# The demo of the modules just installed. On a lab without a demo journal yet, all of it. With one,
# a bare `webx:demo` refuses ("already installed"), so each new module by name — and a module
# with no demo content (the cart, the visitors) is refused by `--module` for that, which is no
# failure: there is nothing of it to seed.
seed_demo_of() {
    if [ ! -f "$SITE/storage/app/webx-demo.json" ]; then
        artisan webx:demo --no-interaction
        return
    fi
    local id output
    while read -r id _; do
        [ -n "$id" ] || continue
        if output="$(artisan webx:demo --module="$id" --no-interaction 2>&1)"; then
            note "demo of $id seeded"
        elif grep -q 'No installed module with demo content is called' <<< "$output"; then
            note "$id has no demo content"
        else
            printf '%s\n' "$output" >&2
            fail "webx:demo --module=$id failed"
        fi
    done <<< "$1"
}

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
    [ -f "$SITE/artisan" ] || fail "$SITE is not a site; the platform makes it (site-create, import-dev) — ask the person who runs it."
    if [ -e "$SITE/.git" ]; then
        fail "$SITE is a git checkout; the lab has no history of its own. Move it away or set LAB_NAME."
    fi
}

lab_environment() {
    # The platform says the database, Redis, APP_URL and APP_KEY to the container, over .env; what
    # it does not say is the lab's. Two sites of the same name on one Redis read each other's cache
    # with the skeleton's default prefix (the slug of APP_NAME).
    env_set CACHE_STORE redis
    env_set CACHE_PREFIX "${ID}_cache_"
    env_set MANTICORE_TABLE_PREFIX "$ID"
    env_set APP_URL "$URL"
    # The demo catalogue sells through the cart: without a currency the cart refuses every write.
    [ -n "$(env_get WEBX_CATALOG_CURRENCY)" ] || env_set WEBX_CATALOG_CURRENCY EUR
    # The test social network of module-users (APP_ENV=local only): the sign-in through a network is
    # walked on the lab without the keys of a real console.
    [ -n "$(env_get WEBX_USERS_DEV_ENABLED)" ] || env_set WEBX_USERS_DEV_ENABLED true
    apply_lab_env
}

# The lines of .lab-env over the template's, last, so that setup and the template lose to them.
apply_lab_env() {
    [ -f "$LAB_ENV" ] || return 0
    local line key names=()
    while IFS= read -r line || [ -n "$line" ]; do
        [[ "$line" =~ ^([A-Za-z_][A-Za-z0-9_]*)= ]] || continue
        key="${BASH_REMATCH[1]}"
        env_set "$key" "${line#*=}"
        names+=("$key")
    done < "$LAB_ENV"
    if [ ${#names[@]} -gt 0 ]; then note ".lab-env over .env: ${names[*]}"; fi
}

# The files of .lab-config/ into config/: every one after setup (`all`), only the missing ones
# otherwise — an edit made in config/ by hand is not taken back.
apply_lab_config() {
    [ -d "$LAB_CONFIG" ] && [ -d "$SITE/config" ] || return 0
    local file name copied=()
    for file in "$LAB_CONFIG"/*; do
        [ -f "$file" ] || continue
        name="$(basename "$file")"
        if [ "$1" = all ] || [ ! -f "$SITE/config/$name" ]; then
            cp "$file" "$SITE/config/$name"
            copied+=("$name")
        fi
    done
    if [ ${#copied[@]} -gt 0 ]; then note ".lab-config/ into config/: ${copied[*]}"; fi
}

admin_password_file() {
    if [ ! -f "$SECRET" ]; then
        local password
        password="$(od -An -tx1 -N12 /dev/urandom | tr -d ' \n')"
        ( umask 077 && printf '%s\n%s\n' "$ADMIN" "$password" > "$SECRET" )
        chmod 600 "$SECRET"
    fi
}

# Every webx-ui/* the site requires (and the modules given as `id package` lines) at the version
# of this checkout, in composer.json and in the platform's composer.live.json alike: a release
# raises the version of every package here, and the constraints setup wrote would hold the site on
# the previous one. Rewrites the files only; `wxp live sync` installs.
require_from_checkout() {
    node -e '
        const { readFileSync, writeFileSync, existsSync } = require("node:fs")
        const [site, monorepo, wanted] = process.argv.slice(1)
        const version = JSON.parse(readFileSync(monorepo + "/php/package.json", "utf8")).version
        const added = wanted.split("\n").map((line) => line.split(" ")[1]).filter(Boolean)
        for (const name of ["composer.json", "composer.live.json"]) {
            const file = site + "/" + name
            if (!existsSync(file)) continue
            const composer = JSON.parse(readFileSync(file, "utf8"))
            composer.require ??= {}
            for (const pkg of added) composer.require[pkg] = "^" + version
            for (const pkg of Object.keys(composer.require)) {
                if (pkg.startsWith("webx-ui/")) composer.require[pkg] = "^" + version
            }
            writeFileSync(file, JSON.stringify(composer, null, 4) + "\n")
        }
    ' "$SITE" "$MONOREPO" "$1"
}

# Modules of the catalogue the lab does not have, as `id package` lines.
missing_modules() {
    artisan webx:modules --json | node -e '
        let input = ""
        process.stdin.on("data", (chunk) => (input += chunk)).on("end", () => {
            const { modules } = JSON.parse(input)
            process.stdout.write(modules.filter((module) => !module.installed).map((module) => module.id + " " + module.package).join("\n"))
        })
    '
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

http_check() {
    local path code
    for path in / /cms; do
        code="000"
        for _ in $(seq 1 30); do
            code="$(curl -s -o /dev/null -w '%{http_code}' "$URL$path" || true)"
            [ "$code" = "000" ] || [ "$code" = "502" ] || [ "$code" = "503" ] || break
            sleep 1
        done
        [ "$code" = "200" ] || fail "GET $URL$path answered $code"
        note "GET $URL$path — 200"
    done
}

# Setup on an empty database: every module of the catalogue, theme-default, block types, the demo.
# The modules are in composer.json already, so setup asks Composer for nothing; the password goes
# from .lab-admin to setup inside the container and never through the command line of wxp.
build() {
    guard_site
    step "webx:setup — every module of the catalogue, theme-default"
    admin_password_file
    lab_environment
    apply_lab_config all
    # shellcheck disable=SC2016 # expanded in the container, with the platform's environment
    lab sh -c 'WEBX_ADMIN_PASSWORD="$(sed -n 2p .lab-admin)" php artisan webx:setup --no-interaction \
        --name="WebX Lab" --domain="$1" --locales=en,ru,pl --modules=all \
        --db-connection=mariadb --db-host="$DB_HOST" --db-port="$DB_PORT" \
        --db-username="$DB_USERNAME" --db-password="$DB_PASSWORD" --db="$DB_DATABASE" \
        --admin="$2" --admin-name="Lab admin" --no-build' setup "$URL" "$ADMIN"
    # Setup writes the address it was given and publishes the configs of the modules; what it may
    # rewrite of ours goes back.
    lab_environment
    apply_lab_config all

    step 'Block types and the demo of every module'
    artisan webx:blocks:import --theme --publish --no-interaction
    artisan webx:blocks:offered --install --no-interaction
    artisan webx:demo --no-interaction
    date -u +%FT%TZ > "$BUILT"
}

refresh() {
    guard_site
    [ -f "$BUILT" ] || fail "the lab is not set up — scripts/lab-site.sh reset"
    local started=$SECONDS done=()

    local branch head
    branch="$(git -C "$MONOREPO" rev-parse --abbrev-ref HEAD)"
    head="$(git -C "$MONOREPO" rev-parse --short HEAD)"

    step 'What the lab keeps of its own (.lab-env, .lab-config/)'
    lab_environment
    apply_lab_config missing

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

    step 'Composer: webx-ui/* from this checkout (wxp live sync)'
    # Before the first artisan of ours: vendor/webx-ui/* are links, so a package here already
    # calls a library it gained, and the lab cannot boot until Composer has loaded that library.
    # The sync is the core's: Composer by composer.live.json, migrations, caches, queue restart.
    require_from_checkout ''
    wxp live sync "$NAME"

    step 'Modules the catalogue has and the lab does not'
    local missing
    missing="$(missing_modules)"
    if [ -n "$missing" ]; then
        require_from_checkout "$missing"
        wxp live sync "$NAME"
        [ -z "$(missing_modules)" ] || fail "wxp live sync did not install $(missing_modules | cut -d' ' -f2 | tr '\n' ' ')"
        done+=("installed $(echo "$missing" | cut -d' ' -f1 | tr '\n' ' ')")
    else
        note 'none'
    fi

    step 'Migrations'
    artisan migrate --force --no-interaction

    if [ -n "$missing" ]; then
        step 'Block types and demo of the new modules'
        artisan webx:blocks:offered --install --no-interaction
        seed_demo_of "$missing"
    fi

    step 'Panel and the site build (wxp node)'
    # The lab keeps no entry of its own, so it is written over: `--sync` alone never adds a module
    # to an existing admin.ts (docs/pitfalls/packages-and-demo-sites.md).
    artisan webx:panel --sync --force --no-interaction
    point_npm "$SITE"
    wxp node "$NAME" -- sh -c 'npm install --no-audit --no-fund && npm run build'

    step 'Theme, caches, restart'
    artisan webx:theme:sync --no-interaction
    artisan optimize:clear --no-interaction
    artisan cache:clear --no-interaction
    artisan queue:restart --no-interaction
    wxp env restart "$NAME" dev

    step 'Check'
    artisan webx:doctor || fail 'webx:doctor found what is listed above'
    http_check

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

# The core makes dev again empty and checks it answers over HTTP — which an empty database never
# does (500 until setup). So the operation failing on the HTTP check of its last step, with every
# step before done, is the reset we asked for; any other failure stops here.
dev_reset() {
    local output op
    if output="$(wxp dev reset "$NAME" 2>&1)"; then
        printf "%s\n" "$output"
        return
    fi
    printf "%s\n" "$output" >&2
    op="$(grep -o "op-[0-9]\{8\}-[0-9]\{6\}-[0-9a-f]\{6\}" <<< "$output" | head -n1)"
    [ -n "$op" ] || fail "wxp dev reset $NAME failed"
    wxp --json op get "$op" | node -e '
        let input = ""
        process.stdin.on("data", (chunk) => (input += chunk)).on("end", () => {
            const op = JSON.parse(input)
            const steps = op.steps ?? op.operation?.steps ?? []
            const last = steps[steps.length - 1]
            const before = steps.slice(0, -1).every((step) => step.status === "done")
            process.exit(before && last?.name === "up" && /HTTP 5\d\d/.test(last.error ?? "") ? 0 : 1)
        })
    ' || fail "wxp dev reset $NAME failed ($op)"
    note "$op: dev is up on an empty database; its 500 is what setup is for"
}

reset() {
    guard_site
    step "Reset $NAME: wxp dev reset (container, volumes, database; the code in ~/work stays)"
    dev_reset
    # vendor/ and node_modules/ are in the code and stay; the database is empty, setup comes first.
    # The demo journal is in the code too and would say the empty database is seeded.
    rm -f "$BUILT" "$STATE" "$SITE/storage/app/webx-demo.json"
    build
    refresh
}

status() {
    printf '%s — %s\n' "$NAME" "$URL"
    if [ ! -f "$STATE" ]; then
        printf '   never refreshed%s\n' "$([ -f "$BUILT" ] && echo ' — scripts/lab-site.sh refresh' || echo '; not set up — scripts/lab-site.sh reset')"
    else
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
    wxp status "$NAME" | sed 's/^/   /'
    # A sleeping dev wakes on the request, so a first answer may take the platform a few seconds.
    local code
    code="$(curl -s -o /dev/null -m 60 -w '%{http_code}' "$URL/" || true)"
    printf '   GET / — %s\n' "$code"
}

case "${1:-}" in
    refresh) URL="$(lab_url)"; refresh ;;
    reset) URL="$(lab_url)"; reset ;;
    status) URL="$(lab_url)"; status ;;
    *) sed -n '9,12p' "$0" | sed 's/^# \{0,1\}//' >&2; exit 2 ;;
esac
