#!/usr/bin/env bash
#
# Serve the Laravel application the admin front end is developed against.
# Provision it first with scripts/php-dev-app.sh.

set -euo pipefail

APP="${APP_PATH:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)/webx-cms.local}"
PHP_BIN="${PHP_BIN:-php}"

if [ ! -f "$APP/artisan" ]; then
    printf 'No application at %s — run scripts/php-dev-app.sh first.\n' "$APP" >&2
    exit 1
fi

exec "$PHP_BIN" "$APP/artisan" serve --host=127.0.0.1 --port="${PORT:-8000}"
