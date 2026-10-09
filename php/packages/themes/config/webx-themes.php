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

];
