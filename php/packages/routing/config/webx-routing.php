<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Types
    |---------------------------------------------------------------------------
    |
    | A module registers its own types in a service provider; this is where a
    | site overrides one of them without touching the module. Today that means
    | the formatter — the shape of the address:
    |
    |     'types' => [
    |         'article' => ['formatter' => WebxUi\Routing\Formatters\SlugId::class],
    |     ],
    |
    | Changing it does not move the addresses that already exist. That is what
    | `webx:routes:rebuild --type=article` is for: it recomputes every path and
    | leaves the old ones behind as aliases, so no external link dies.
    |
    */

    'types' => [],

    /*
    |---------------------------------------------------------------------------
    | The fallback route
    |---------------------------------------------------------------------------
    |
    | The registry answers through `Route::fallback()`, which by definition is
    | tried only when nothing else matched — a project's own `/search` wins with
    | no ordering to arrange and no catch-all shadowing anything.
    |
    | Turn this off to call the resolver from a route of your own. Our provider
    | boots before the application's, so a project cannot lose this race by
    | accident.
    |
    */

    'fallback' => true,

    /*
    |---------------------------------------------------------------------------
    | Reserved addresses
    |---------------------------------------------------------------------------
    |
    | Checked when an entity is saved, not when a request is resolved: losing
    | silently to a live route is worse than telling the editor straight away.
    |
    | The router is asked first — a project that adds a screen keeps this list
    | true without editing it — and these are the addresses no route describes:
    | directories served by the web server, and names kept for later. The panel's
    | own prefix is added at runtime from `module-admin`.
    |
    */

    'reserved' => [
        'storage',
        'build',
        'vendor',
    ],

];
