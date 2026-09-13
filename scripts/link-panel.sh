#!/usr/bin/env bash
#
# Point the development application's panel packages at this checkout instead of the registry.
#
# The application keeps building the panel the way a real site does — its own Vite, its own
# entry, hashed filenames — and only where the packages come from changes. Going back is the
# same script with `--registry`.
#
#   scripts/link-panel.sh
#   scripts/link-panel.sh --registry
#   APP_PATH=/somewhere scripts/link-panel.sh
#
# The application at ../webx-cms.local is a deployed site now, and its Composer half has to move
# with the npm one: a manifest naming this checkout cannot be built anywhere else, and its deploy
# rejects one that reaches its repository. That application owns the switch — this script hands
# over to it when it is there, and does the npm half alone when it is not.

set -euo pipefail

MONOREPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP="${APP_PATH:-$(cd "$MONOREPO/.." && pwd)/webx-cms.local}"
MODE="${1:-local}"

PACKAGES=(core tokens admin module-auth)

if [ ! -f "$APP/package.json" ]; then
    printf 'No application at %s — run scripts/php-dev-app.sh first.\n' "$APP" >&2
    exit 1
fi

step() { printf '\n\033[1m== %s\033[0m\n' "$1"; }

if [ "$MODE" != "--registry" ]; then
    step 'Build the packages in this checkout'
    ( cd "$MONOREPO" && pnpm build )
fi

if [ -f "$APP/scripts/packages.mjs" ]; then
    step "Point $APP at $([ "$MODE" = "--registry" ] && echo 'the registry' || echo 'this checkout')"
    ( cd "$APP" && node scripts/packages.mjs "$([ "$MODE" = "--registry" ] && echo registry || echo local)" )
else
    # A freshly provisioned application has no switch of its own yet: npm only, and say so.
    if [ "$MODE" = "--registry" ]; then
        step 'Back to the published packages'
        ( cd "$APP" && npm install "${PACKAGES[@]/#/@webx-ui/}" --save )
    else
        step "Link them into $APP"
        # A relative specifier: npm symlinks a `file:` directory, so rebuilding a package here is
        # enough — the application only has to build itself again.
        RELATIVE="$(node -e "
            const { relative, sep } = require('node:path')
            process.stdout.write(relative(process.argv[1], process.argv[2]).split(sep).join('/'))
        " "$APP" "$MONOREPO")"

        specifiers=()
        for package in "${PACKAGES[@]}"; do
            specifiers+=("@webx-ui/${package}@file:${RELATIVE}/packages/${package}")
        done

        ( cd "$APP" && npm install "${specifiers[@]}" --save )
    fi

    printf '   Composer side untouched: this application has no scripts/packages.mjs.\n'
fi

step 'Build the panel'
( cd "$APP" && npm run build )

printf '\n\033[32m== Done. Rebuild after changing a package: pnpm build here, npm run build there.\033[0m\n'
