<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Cache on versioned keys
    |---------------------------------------------------------------------------
    |
    | What the site remembers between requests lives on versioned keys: every
    | group of data (`menu`, `settings`, `routes`, …) has a version in the
    | cache, a save of one of its models moves it, and an entry built from the
    | group goes with the old version. Works on every store, `file` and
    | `database` included; `cache:clear` is never needed and never called.
    |
    | `ttl` is how long an entry lives unless the code says otherwise. Off, the
    | code computes everything on every request — for a test, not for a site.
    |
    */

    'enabled' => (bool) env('WEBX_CACHE_ENABLED', true),

    'ttl' => (int) env('WEBX_CACHE_TTL', 86400),

];
