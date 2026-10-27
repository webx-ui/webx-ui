<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | On or off
    |---------------------------------------------------------------------------
    |
    | The debug bar at the bottom of the site's pages, for an administrator with
    | the permission `debug.view`. In production the package does nothing at
    | all, whatever this says: no listener, no route, no MCP tool — unless
    | `mcp.production` below opens `debug_page` there, and then this switches
    | the tool off as it does the bar elsewhere.
    |
    */

    'enabled' => (bool) env('WEBX_DEBUGBAR', true),

    /*
    |---------------------------------------------------------------------------
    | Reports
    |---------------------------------------------------------------------------
    |
    | Where a report is kept (a cache store; null is the site's default), for how
    | many seconds, and how many of the latest an administrator keeps. Nothing is
    | migrated: an expired report is the store's to sweep.
    |
    */

    'store' => env('WEBX_DEBUGBAR_STORE'),

    'ttl' => 600,

    'keep' => 20,

    /*
    |---------------------------------------------------------------------------
    | What is worth a look
    |---------------------------------------------------------------------------
    |
    | A statement this slow (milliseconds) is marked slow; a shape of statement
    | repeated this many times is marked as a likely N+1.
    |
    */

    'slow_ms' => 50,

    'repeat' => 3,

    /*
    |---------------------------------------------------------------------------
    | Limits
    |---------------------------------------------------------------------------
    |
    | Past these the report keeps counting but stops writing down, and says it
    | was truncated.
    |
    */

    'limits' => [
        'queries' => 1000,
        'value' => 500,
        'sql' => 10000,
        'cache' => 2000,
        'log' => 200,
        'views' => 500,
        'blocks' => 2000,
    ],

    /*
    |---------------------------------------------------------------------------
    | Masked
    |---------------------------------------------------------------------------
    |
    | Values never written into a report: bound to these columns (`*` is any
    | run), any value of a statement on these tables, these headers. Context of
    | the log and query parameters of outgoing requests go by the same columns.
    |
    */

    'mask' => [
        'columns' => [
            'password', 'remember_token', 'token', 'secret', 'api_key', 'access_token',
            'refresh_token', '*_secret', '*_token', '*password*',
        ],
        'tables' => [
            'sessions', 'cache', 'cache_locks', 'oauth_*', 'personal_access_tokens', 'jobs',
            'failed_jobs', 'password_reset_tokens',
        ],
        'headers' => [],
    ],

    /*
    |---------------------------------------------------------------------------
    | Events
    |---------------------------------------------------------------------------
    |
    | Events counted in the tab «Other», by class. Named one by one rather than
    | every event at once, which would cost on every one of them.
    |
    */

    'events' => [
        'WebxUi\\Admin\\Events\\StoredContentRewritten',
        'Illuminate\\Auth\\Events\\Login',
        'Illuminate\\Auth\\Events\\Failed',
    ],

    /*
    |---------------------------------------------------------------------------
    | debug_page
    |---------------------------------------------------------------------------
    |
    | The MCP tool asks the site for a page over HTTP, as a visitor would. A site
    | that cannot reach itself by its own name (inside Docker, behind NAT) names
    | the address to connect to instead; the Host stays the site's.
    |
    | `production` opens the tool, and only the tool, on a site running as
    | production — a closed stand that has to be measured where it runs. The
    | bar, its cookie and the API of reports stay off there whatever this says,
    | and a report made there keeps no value of any statement. Off by default;
    | `php artisan webx:doctor` says so while it is on.
    |
    */

    'mcp' => [
        'resolve_to' => env('WEBX_DEBUGBAR_RESOLVE_TO'),
        'timeout' => 30,
        'production' => filter_var(env('WEBX_DEBUG_MCP_IN_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN),
    ],

];
