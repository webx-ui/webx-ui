<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | The first segment of every services address
    |---------------------------------------------------------------------------
    |
    | The index is at `services`, a category at `services/implants`, a service at
    | `services/implants-turnkey` — the category and the service on one level, so
    | a service in three categories still has one address. Their slugs share that
    | level too: a category and a service that want the same slug are refused,
    | the second of them under its own field.
    |
    | Empty puts categories and services at the root of the site beside the pages,
    | and the index route is then not registered at all: `/` belongs to the site,
    | and a list of services on it is a page the site writes.
    |
    | Changing this afterwards is `php artisan webx:routes:rebuild --type=service
    | --type=service-category`: the paths are recomputed and the old ones stay
    | behind as aliases that redirect.
    |
    */

    'prefix' => env('WEBX_SERVICES_PREFIX', 'services'),

    /*
    |---------------------------------------------------------------------------
    | Categories
    |---------------------------------------------------------------------------
    |
    | `blocks` gives a category page a Blocks tab in the panel — for the site whose
    | category pages are landing pages. Off by default, because the package's view
    | prints them and a site that rewrote the view may not: an editor filling in a
    | tab the page never shows is worse than not having the tab.
    |
    */

    'categories' => [
        'blocks' => (bool) env('WEBX_SERVICES_CATEGORY_BLOCKS', false),
    ],

    /*
    |---------------------------------------------------------------------------
    | The views the public half is printed with
    |---------------------------------------------------------------------------
    |
    | A site keeps its own markup here. Until it has written these views, the
    | package's own are used — bare documents that serve a catalogue rather than
    | an error on a fresh installation.
    |
    | `index` is handed `$categories` (each with its `services` loaded, in the
    | order of that category) and `$uncategorised`; `category` is handed
    | `$category` and `$services`; `service` is handed `$service` and `$category`
    | — the main one, or null.
    |
    */

    'views' => [
        'index' => env('WEBX_SERVICES_VIEW_INDEX', 'services.index'),
        'category' => env('WEBX_SERVICES_VIEW_CATEGORY', 'services.category'),
        'service' => env('WEBX_SERVICES_VIEW_SERVICE', 'services.service'),
    ],

    /*
    |---------------------------------------------------------------------------
    | The layout the three public pages stand in
    |---------------------------------------------------------------------------
    |
    | The name of a Blade component: `'layout'` for the `<x-layout>` a site keeps
    | in `resources/views/components/layout.blade.php`. Empty prints the package's
    | own `webx-services::standalone` — a bare document.
    |
    | Two slots and no more: `head`, and the default slot for the content, with
    | `@stack('head')` beside `{{ $head }}` in the layout. `php artisan
    | webx:doctor` says so if it is missing.
    |
    */

    'layout' => env('WEBX_SERVICES_LAYOUT'),

    /*
    |---------------------------------------------------------------------------
    | What the index route runs through
    |---------------------------------------------------------------------------
    |
    | The same list the address registry answers through, because it is the same
    | kind of page: a session, and the decision about which language this request
    | is in.
    |
    */

    'middleware' => ['web', 'webx.locale'],

];
