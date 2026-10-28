# shellcheck shell=bash
#
# What the scripts that build a site wired to this checkout share: scripts/starter-site.sh (the
# throwaway starter) and scripts/lab-site.sh (the permanent lab in devbox). Sourced, not run.
#
# Expects MONOREPO (this checkout) and PHP_BIN; COMPOSER_PHAR is optional. Every function that
# takes a site takes its directory.

step() { printf '\n\033[1m== %s\033[0m\n' "$1"; }
note() { printf '   %s\n' "$1"; }
fail() { printf '\n\033[31mFAILED: %s\033[0m\n' "$1" >&2; exit 1; }

on_windows() { [ "${OS:-}" = "Windows_NT" ]; }

# A path every program understands: C:/x/y on Windows, the path itself elsewhere. Git Bash
# rewrites a /c/... argument for the programs it starts, but never one inside a JSON string.
native() { if on_windows; then cygpath -m "$1"; else printf '%s' "$1"; fi; }

composer_bin() {
    if [ -n "${COMPOSER_PHAR:-}" ]; then "$PHP_BIN" "$COMPOSER_PHAR" "$@"; else composer "$@"; fi
}

# Remove a link without following it. `rm -rf` through a link into this checkout deletes the
# sources (CLAUDE.md §4), and on Windows the links Composer and npm make are junctions, which
# only `rmdir` is sure to take away on their own.
unlink_one() {
    if on_windows; then cmd //c rmdir "$(cygpath -w "$1")" > /dev/null 2>&1 || rm -f "$1"; else rm -f "$1"; fi
}

# Delete a site directory whole: every link taken out first, so the removal never walks into
# this checkout through vendor/webx-ui/* or node_modules/@webx-ui/*.
remove_site_tree() {
    local site="$1"
    local canary="$MONOREPO/php/packages/themes/composer.json"
    local links
    # Every link, wherever it points: vendor/webx-ui/* and node_modules/@webx-ui/* into this
    # checkout, public/storage into the site itself. Taking a link away never touches its target.
    links="$(find "$site" -type l 2>/dev/null || true)"
    while IFS= read -r link; do
        [ -n "$link" ] && unlink_one "$link"
    done <<< "$links"

    # Anything still pointing out of the directory stops the removal rather than going with it.
    if [ -n "$(find "$site" -type l 2>/dev/null | head -1)" ]; then
        find "$site" -type l >&2
        fail 'links are left in the site; remove them by hand (rmdir on Windows), then run this again.'
    fi

    rm -rf "$site"
    [ -f "$canary" ] || fail "the removal reached this checkout: $canary is gone. Restore it with git."
    note "directory removed, $(echo "$links" | grep -c . || true) links taken out first"
}

# Drop the database, and with `create` make it again empty. The user needs rights on that
# database only: CREATE and DROP of the database itself are among them.
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

# The version every package of this checkout goes by (one for all, WEBX_UI_PHP_RELEASE.md).
monorepo_version() {
    node -p "require(process.argv[1]).version" "$(native "$MONOREPO/php/package.json")"
}

# A new site from the skeleton in this checkout, without installing anything yet.
create_skeleton() {
    local site="$1" version
    version="$(monorepo_version)"
    local repository
    repository="$(node -e '
        const [url, version] = process.argv.slice(1)
        process.stdout.write(JSON.stringify({ type: "path", url, options: { symlink: false, versions: { "webx-ui/site": version } } }))
    ' "$(native "$MONOREPO/php/site")" "$version")"
    (
        cd "$(dirname "$site")"
        composer_bin create-project webx-ui/site "$(basename "$site")" "$version" --repository="$repository" \
            --no-install --no-scripts --no-interaction
    )
}

# What `scripts/packages.mjs local` does on the other demo sites: the registry for everything but
# webx-ui/*, and the packages of this checkout under the version the monorepo gives them.
# Rewrites the repositories only; installing is the caller's.
point_composer() {
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
    ' "$(native "$1")" "$(native "$MONOREPO")"
}

# Setup and `webx:module:add` write registry versions; every @webx-ui/* becomes the package in
# this checkout, relative so that npm links it and a rebuild here only needs `npm run build`
# there. Rewrites package.json only; installing is the caller's.
point_npm() {
    [ -d "$MONOREPO/packages/core/dist" ] || fail "packages/core/dist is missing — run pnpm build in $MONOREPO first."
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
    ' "$(native "$1")" "$(native "$MONOREPO")"
}
