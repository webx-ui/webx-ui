<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | The view a page is printed with
    |---------------------------------------------------------------------------
    |
    | The handler hands the entity to this view as `$page`. A site keeps its
    | layout, its header and its footer here; the content itself is whatever
    | `$page->renderBlocks()` prints, so the view is a frame around one line.
    |
    | Until the site has written it, the package's own `webx-pages::show` is
    | used: a bare document with the blocks, the SEO head and nothing else, so
    | that a fresh installation serves a page rather than an error.
    |
    */

    'view' => env('WEBX_PAGES_VIEW', 'pages.show'),

];
