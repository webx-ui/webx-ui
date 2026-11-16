<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | The theme
    |---------------------------------------------------------------------------
    |
    | The top of the chain: a path to the site's local theme (relative to the
    | project root, usually 'theme') or the Composer name of a packaged theme
    | ('webx-ui/theme-default'). Every layer below comes from the `uses` of
    | the one above it.
    |
    | Empty means the site has no theme: nothing is added to the view paths
    | and the site's own resources/views is all there is.
    |
    */

    'theme' => env('WEBX_THEME', ''),

    /*
    |---------------------------------------------------------------------------
    | The critical CSS
    |---------------------------------------------------------------------------
    |
    | The file `php artisan webx:theme:critical` writes: the rules the first
    | screen of the pages named by "critical" in theme.json needs. While it is
    | there, `@webxTheme` prints it inline and the page's stylesheets stop
    | holding the first paint. Relative to the project root. No file, no change.
    |
    */

    'critical' => env('WEBX_THEME_CRITICAL', 'theme/critical.css'),

];
