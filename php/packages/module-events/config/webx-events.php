<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | The first segment of every events address
    |---------------------------------------------------------------------------
    |
    | A category is at `events/cooking-classes`, an event at `events/spring-class`
    | — on one level, so an event in two categories still has one address. Their
    | slugs share that level too: a category and an event that want the same slug
    | are refused, the second of them under its own field.
    |
    | Never empty. Events in the root of the site would argue with the tree of
    | pages over every address, and the package refuses to boot rather than let
    | them.
    |
    | Changing this afterwards is `php artisan webx:routes:rebuild --type=event
    | --type=event-category`: the paths are recomputed and the old ones stay behind
    | as aliases that redirect.
    |
    */

    'prefix' => env('WEBX_EVENTS_PREFIX', 'events'),

    /*
    |---------------------------------------------------------------------------
    | The index page
    |---------------------------------------------------------------------------
    |
    | On, the package answers the prefix itself with the events to come. Off, the
    | route is not registered and the address is free: a page with the slug
    | `events` takes it, with whatever the site wants on it. Categories and events
    | keep their addresses under the prefix either way, and their trail goes
    | through whatever stands there.
    |
    */

    'index' => (bool) env('WEBX_EVENTS_INDEX', true),

    /*
    |---------------------------------------------------------------------------
    | Events on one page of a list
    |---------------------------------------------------------------------------
    |
    | The index and a category page, both of which list only the events to come.
    | The past ones are printed where a site asks for them: `events()->past()`.
    |
    */

    'per-page' => (int) env('WEBX_EVENTS_PER_PAGE', 24),

    /*
    |---------------------------------------------------------------------------
    | The currency of the prices
    |---------------------------------------------------------------------------
    |
    | One for the whole site, as an ISO 4217 code: `EUR`, `HKD`. Only the markup
    | reads it — the page prints the price the editor wrote in words — and an
    | event's number goes into `offers` only when this is set.
    |
    */

    'currency' => env('WEBX_EVENTS_CURRENCY'),

    /*
    |---------------------------------------------------------------------------
    | The views the public half is printed with
    |---------------------------------------------------------------------------
    |
    | A site keeps its own markup here. Until it has written these views, the
    | package's own are used. An event page is one view of parts, each its own
    | `@include`, so a site can publish and rewrite one part — the facts, the
    | booking — and leave the rest to the package:
    |
    |     php artisan vendor:publish --tag=webx-events-views
    |
    | The list itself (`partials/list`) is shared by the index and a category
    | page: rewrite it once and both change.
    |
    */

    'views' => [
        'index' => env('WEBX_EVENTS_VIEW_INDEX', 'events.index'),
        'category' => env('WEBX_EVENTS_VIEW_CATEGORY', 'events.category'),
        'event' => env('WEBX_EVENTS_VIEW_EVENT', 'events.event'),
    ],

    /*
    |---------------------------------------------------------------------------
    | The layout the public pages stand in
    |---------------------------------------------------------------------------
    |
    | The name of a Blade component: `'layout'` for the `<x-layout>` a site keeps
    | in `resources/views/components/layout.blade.php`. Empty prints the package's
    | own `webx-events::standalone` — a bare document. Two slots: `head`, and the
    | default one, with `@stack('head')` beside `{{ $head }}`.
    |
    */

    'layout' => env('WEBX_EVENTS_LAYOUT'),

    /*
    |---------------------------------------------------------------------------
    | The trail above the content
    |---------------------------------------------------------------------------
    |
    | Whether the package's views print the crumbs a reader sees. The
    | BreadcrumbList in the <head> is `module-seo`'s and stays either way.
    |
    */

    'breadcrumbs' => (bool) env('WEBX_EVENTS_BREADCRUMBS', true),

    /*
    |---------------------------------------------------------------------------
    | What the index and the calendar files run through
    |---------------------------------------------------------------------------
    */

    'middleware' => ['web', 'webx.locale'],

];
