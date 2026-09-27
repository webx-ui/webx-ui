<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | The first segment of every outlet's address
    |---------------------------------------------------------------------------
    |
    | An outlet is at `press/tatler-asia`. Never empty: outlets in the root of the
    | site would argue with the tree of pages over every address, and the package
    | refuses to boot rather than let them.
    |
    | The prefix itself is not the module's. `/press` is a page of `module-pages`
    | with the catalogue block on it, and an outlet's trail goes through whatever
    | stands there.
    |
    | Changing this afterwards is `php artisan webx:routes:rebuild
    | --type=press-outlet`: the paths are recomputed and the old ones stay behind
    | as aliases that redirect.
    |
    */

    'prefix' => env('WEBX_PRESS_PREFIX', 'press'),

    /*
    |---------------------------------------------------------------------------
    | A page for every outlet
    |---------------------------------------------------------------------------
    |
    | Off, there is no page, no address, no entry in the sitemap and nothing for a
    | menu to point at: a logo in a block leads to the outlet's own site instead.
    | The slug disappears from the form with it.
    |
    */

    'pages' => (bool) env('WEBX_PRESS_PAGES', true),

    /*
    |---------------------------------------------------------------------------
    | The kinds of article
    |---------------------------------------------------------------------------
    |
    | The keys an article may be marked with, in the order the form and the
    | catalogue block list them. The words are `webx-press::kinds.<key>`: a kind
    | of the site's own is a line here and a line in
    | `lang/vendor/webx-press/<locale>/kinds.php`.
    |
    | A key taken out of this list is "no kind" on every article that had it, and
    | refused on the next save of one — the editor picks another.
    |
    | The offered blocks take their choice of kinds from this list when they are
    | installed. A kind added later is added to them in the panel, by hand.
    |
    */

    'kinds' => ['mention', 'interview', 'expert_comment', 'authored'],

    /*
    |---------------------------------------------------------------------------
    | The view an outlet's page is printed with
    |---------------------------------------------------------------------------
    |
    | A site keeps its own markup here. Until it has written the view, the
    | package's own is used. The page is one view of parts, each its own
    | `@include`, so a site can publish and rewrite one part — the list of
    | articles, say — and leave the rest to the package:
    |
    |     php artisan vendor:publish --tag=webx-press-views
    |
    */

    'views' => [
        'outlet' => env('WEBX_PRESS_VIEW_OUTLET', 'press.outlet'),
    ],

    /*
    |---------------------------------------------------------------------------
    | The layout the page stands in
    |---------------------------------------------------------------------------
    |
    | The name of a Blade component: `'layout'` for the `<x-layout>` a site keeps
    | in `resources/views/components/layout.blade.php`. Empty prints the package's
    | own `webx-press::standalone` — a bare document. Two slots: `head`, and the
    | default one, with `@stack('head')` beside `{{ $head }}`.
    |
    */

    'layout' => env('WEBX_PRESS_LAYOUT'),

    /*
    |---------------------------------------------------------------------------
    | The trail above the content
    |---------------------------------------------------------------------------
    |
    | Whether the package's view prints the crumbs a reader sees. The
    | BreadcrumbList in the <head> is `module-seo`'s and stays either way.
    |
    */

    'breadcrumbs' => (bool) env('WEBX_PRESS_BREADCRUMBS', true),

];
