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

    'api_middleware' => ['api'],

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

];
