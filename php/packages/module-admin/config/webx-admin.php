<?php

declare(strict_types=1);
use WebxUi\Admin\Links\SiteRoutes;

return [

    /*
    |---------------------------------------------------------------------------
    | Panel title
    |---------------------------------------------------------------------------
    |
    | Shown in the browser tab and wherever the front end names the panel.
    |
    */

    'title' => env('WEBX_ADMIN_TITLE', 'WebX UI'),

    /*
    |---------------------------------------------------------------------------
    | Paths
    |---------------------------------------------------------------------------
    |
    | `path` is where the panel itself answers — everything below it serves the
    | same page, because routing inside the admin belongs to the front end.
    | `api_path` is where its JSON lives, the manifest included.
    |
    */

    'path' => env('WEBX_ADMIN_PATH', 'cms'),

    'api_path' => env('WEBX_ADMIN_API_PATH', 'api/cms'),

    /*
    |---------------------------------------------------------------------------
    | Middleware
    |---------------------------------------------------------------------------
    |
    | Authentication is not this package's business: webx-ui/module-auth adds its
    | middleware here once it is installed. Until then the panel is open, which
    | is fine locally and is not fine anywhere else.
    |
    */

    'middleware' => ['web'],

    'api_middleware' => ['api', 'webx.panel-locale'],

    /*
    |---------------------------------------------------------------------------
    | A password over the site while it is being tested
    |---------------------------------------------------------------------------
    |
    | HTTP Basic in front of every address of the site except the panel, its
    | JSON, the MCP server with its OAuth dance, a block preview under its token
    | and `/.well-known`. Switched on and given no pairs, it lets nobody in.
    |
    |     WEBX_SITE_GATE=true
    |     WEBX_SITE_GATE_USERS="client:secret,tester:other-secret"
    |
    | The pairs come from the environment and nowhere else; a password may have
    | a colon in it but not a comma. `except` takes masks for `Str::is` against
    | the path without its leading slash — `['promo', 'promo/*']` opens one page
    | and everything under it.
    |
    | Files the web server serves itself (`/storage`, `/build`) never reach PHP
    | and stay reachable by a direct link.
    |
    */

    'gate' => [
        'enabled' => env('WEBX_SITE_GATE', false),
        'users' => env('WEBX_SITE_GATE_USERS', ''),
        'except' => [],
    ],

    /*
    |---------------------------------------------------------------------------
    | Links
    |---------------------------------------------------------------------------
    |
    | Where a link field offers a path of this site, it offers the named GET
    | routes that take no parameters. Most of those are nobody's page: an
    | authorisation dance, a discovery document, a script. These masks are what
    | stays out of the list — add your own rather than replacing them, since the
    | defaults are the machinery every installation has.
    |
    */

    'links' => [
        'exclude' => SiteRoutes::EXCLUDE,
    ],

    /*
    |---------------------------------------------------------------------------
    | Navigation groups
    |---------------------------------------------------------------------------
    |
    | A module may name a group it belongs under; this is where the groups are
    | described. The title is a translation key, the icon is a name from the
    | icon set and may be left out — a group without one gets a gear. `system`
    | holds what keeps the panel running — settings, administrators — apart
    | from what the site is about.
    |
    */

    'groups' => [
        'system' => ['title' => 'webx-admin::nav.system', 'icon' => 'gear', 'order' => 900],
    ],

    /*
    |---------------------------------------------------------------------------
    | The panel's own assets
    |---------------------------------------------------------------------------
    |
    | The shell renders an empty page for the front end to fill; these are what
    | fill it. Stylesheets and scripts are told apart by their extension.
    |
    |     'assets' => ['/webx/webx.css', '/webx/webx.js'],
    |
    | An application that builds the panel with Laravel's own Vite names its
    | entry points instead, and gets the dev server and hot reloading with them:
    |
    |     'vite' => ['resources/js/admin.ts'],
    |
    | With neither, the page is deliberately blank — the frame is installed and
    | the panel is not.
    |
    */

    'assets' => [],

    'vite' => [],

    /*
    |---------------------------------------------------------------------------
    | Versions
    |---------------------------------------------------------------------------
    |
    | An entity with `HasVersions` keeps this many publications; the oldest go
    | as new ones are written, pinned ones excepted. `autosaves` is the ring of
    | draft copies kept beside the history as insurance, not as part of it.
    | Lowering a limit after the fact is what `webx:versions:prune` is for.
    |
    */

    'versions' => [
        'limit' => 30,
        'autosaves' => 5,
    ],

    /*
    |---------------------------------------------------------------------------
    | Nightly database backup
    |---------------------------------------------------------------------------
    |
    | A gzipped dump of the database, written to `path` under the root of
    | `disk` at `at` every night and kept for `keep` days. It is insurance and
    | not a restore system: the file lands beside the database it came from, so
    | it survives a mistake and not a dead server. Where the host already takes
    | backups, this is one more copy and no harm; where it does not, it is the
    | only one.
    |
    | Laravel's own `storage/app/.gitignore` already keeps the dumps out of the
    | repository — check that it is still there rather than assuming it, and
    | treat a dump copied anywhere else as what it is: every password hash and
    | every telephone number the site holds, in one file.
    |
    | It needs the system cron running `php artisan schedule:run`; without one
    | nothing happens and the panel says so.
    |
    | `binary` is the path to `mysqldump` or `pg_dump`, which is somewhere else
    | on every machine — left empty, the name is used and `PATH` decides.
    |
    | `column_statistics` stays `null` on purpose. A mysqldump 8 client asks a
    | MariaDB server for column statistics and dies on the answer, while
    | MariaDB's own client does not know the flag that turns them off and dies
    | on that; only the machine knows which pair it has. Set `false` for the
    | first case and leave it alone for the second.
    |
    | `options` are extra flags for that tool, and a container is what they
    | are for: Alpine's `mysql-client` is MariaDB's client, and MariaDB's
    | client offers TLS to a MySQL 8 server and then refuses its self-signed
    | certificate. `WEBX_BACKUP_OPTIONS="--ssl-verify-server-cert=0"` is the
    | whole fix, and only the machine knows it needs one. Space-separated in
    | the environment, a list in a published config.
    |
    | `skip_data` names the tables whose structure is worth keeping and whose
    | rows are not. They are rebuilt by the application, and they are usually
    | most of the file.
    |
    */

    'backup' => [
        'enabled' => env('WEBX_BACKUP_ENABLED', true),
        'at' => env('WEBX_BACKUP_AT', '03:10'),
        'keep' => env('WEBX_BACKUP_KEEP', 30),
        'disk' => env('WEBX_BACKUP_DISK', 'local'),
        'path' => env('WEBX_BACKUP_PATH', 'backups'),
        'binary' => env('WEBX_BACKUP_BINARY'),
        'column_statistics' => null,
        'options' => env('WEBX_BACKUP_OPTIONS', []),

        'skip_data' => [
            'cache',
            'cache_locks',
            'sessions',
            'jobs',
            'job_batches',
            'failed_jobs',
        ],
    ],

];
