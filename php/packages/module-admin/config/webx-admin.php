<?php

declare(strict_types=1);

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

];
