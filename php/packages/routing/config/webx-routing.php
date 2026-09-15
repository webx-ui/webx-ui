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
    | What the fallback route runs through
    |---------------------------------------------------------------------------
    |
    | A route registered outside a group has no middleware at all, and a page of
    | a site needs two things that live in one: a session, and the decision about
    | which language this request is in. `webx.locale` reads the same first
    | segment the resolver strips, so the two can never disagree.
    |
    | A site that serves its public pages some other way — no session, its own
    | language middleware — replaces the list here.
    |
    */

    'middleware' => ['web', 'webx.locale'],

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
    | One consequence worth knowing before it surprises somebody: a fresh Laravel
    | skeleton answers `/` with its welcome route, so no page can take the site
    | root until that route is deleted. That is the rule working, not failing.
    |
    */

    'reserved' => [
        'storage',
        'build',
        'vendor',
    ],

];
