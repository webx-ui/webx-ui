#!/usr/bin/env bash
#
# Build the example panel into the Laravel application's public directory, so the real host
# serves it — same origin, real cookies, no dev server and no proxy.
#
#   scripts/build-panel.sh
#   APP_PATH=/somewhere/else scripts/build-panel.sh
#
# The application is the one scripts/php-dev-app.sh provisions, and its config/webx-admin.php
# has to name the two files:
#
#   'assets' => ['/webx/webx.css', '/webx/webx.js'],

set -euo pipefail

MONOREPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP="${APP_PATH:-$(cd "$MONOREPO/.." && pwd)/webx-cms.local}"
MOUNT="${WEBX_MOUNT:-webx}"

if [ ! -d "$APP/public" ]; then
    printf 'No application at %s — run scripts/php-dev-app.sh first.\n' "$APP" >&2
    exit 1
fi

# A bare name rather than a path: Git Bash rewrites anything shaped like an absolute POSIX
# path on its way into a child process, and `/webx/` would arrive as `C:/Program Files/Git/webx/`.
WEBX_MOUNT="$MOUNT" WEBX_OUT="$APP/public/$MOUNT" \
    pnpm --filter @webx-ui/admin-example build

# Vite writes its own page; the panel is served by Laravel's shell instead.
rm -f "$APP/public/$MOUNT/index.html"

printf '\n\033[32m== Panel built into %s\033[0m\n' "$APP/public/$MOUNT"
