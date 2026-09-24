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

    /*
    |---------------------------------------------------------------------------
    | The layout a page stands in
    |---------------------------------------------------------------------------
    |
    | The name of a Blade component: `'layout'` for the `<x-layout>` a site keeps
    | in `resources/views/components/layout.blade.php`. Empty prints the package's
    | own `webx-pages::standalone` — a bare document, which is the right default
    | for a package that cannot assume the site has a layout at all.
    |
    | The deal is two slots and no more: `head`, and the default slot for the
    | content. A layout is expected to carry `@stack('head')` beside `{{ $head }}`
    | as well — a slot is one place, and what a block type pushes cannot reach it.
    | `php artisan webx:doctor` says so if it does not.
    |
    | This is not the same seam as `view` above: that one replaces the markup of
    | the page, this one only says what it stands in.
    |
    */

    'layout' => env('WEBX_PAGES_LAYOUT'),

    /*
    |---------------------------------------------------------------------------
    | The trail above the content
    |---------------------------------------------------------------------------
    |
    | Whether the package's views print the crumbs a reader sees on a page.
    | Many sites want them in the blog and nowhere else, so this is a switch per
    | module rather than one for the whole site.
    |
    | Only the visible trail: the BreadcrumbList in the <head> is `module-seo`'s
    | (`webx-seo.print.breadcrumbs`) and stays, because it helps a search engine
    | whether or not the page shows it. A site with its own `view` above
    | decides in its own markup and never reads this.
    |
    */

    'breadcrumbs' => (bool) env('WEBX_PAGES_BREADCRUMBS', true),

];
