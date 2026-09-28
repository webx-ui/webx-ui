<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | The first segment of every vacancy's address
    |---------------------------------------------------------------------------
    |
    | A vacancy is at `careers/senior-php-developer`, and the index of the open
    | ones at `careers`. Categories have no addresses of their own: they are the
    | groups and the filter of the index (`careers?category=development`).
    |
    | Never empty. Vacancies in the root of the site would argue with the tree
    | of pages over every address, and the package refuses to boot rather than
    | let them.
    |
    | Changing this afterwards is `php artisan webx:routes:rebuild --type=vacancy`:
    | the paths are recomputed and the old ones stay behind as aliases that
    | redirect.
    |
    */

    'prefix' => env('WEBX_VACANCIES_PREFIX', 'careers'),

    /*
    |---------------------------------------------------------------------------
    | The index page
    |---------------------------------------------------------------------------
    |
    | On, the package answers the prefix itself with the open vacancies, grouped
    | by category. Off, the route is not registered and the address is free: a
    | page with the slug `careers` takes it, with whatever the site wants on it.
    | Vacancies keep their addresses under the prefix either way, and their
    | trail goes through whatever stands there.
    |
    */

    'index' => (bool) env('WEBX_VACANCIES_INDEX', true),

    /*
    |---------------------------------------------------------------------------
    | The country of a new vacancy
    |---------------------------------------------------------------------------
    |
    | An ISO 3166-1 alpha-2 code: `UA`, `PL`. Every vacancy keeps its own — a
    | site hiring in two countries has two — and only the markup reads it: the
    | page prints the city and the address the editor wrote.
    |
    */

    'country' => env('WEBX_VACANCIES_COUNTRY'),

    /*
    |---------------------------------------------------------------------------
    | The currencies a salary can be in
    |---------------------------------------------------------------------------
    |
    | ISO 4217 code => the symbol a page prints. The first is the currency of a
    | new vacancy. The same shape as `webx-tariffs.currencies`.
    |
    | A site that publishes this file keeps its own list whole — the config is
    | merged one level deep. A currency taken out of the list does not lock the
    | vacancies that have it: they save as they are, and print the code where
    | the symbol was. Choosing it for another vacancy is refused.
    |
    */

    'currencies' => [
        'USD' => '$',
        'EUR' => '€',
        'UAH' => '₴',
        'PLN' => 'zł',
    ],

    /*
    |---------------------------------------------------------------------------
    | The views the public half is printed with
    |---------------------------------------------------------------------------
    |
    | A site keeps its own markup here. Until it has written these views, the
    | package's own are used. A vacancy page is one view of parts, each its own
    | `@include`, so a site can publish and rewrite one part — the facts, the
    | place for the application form — and leave the rest to the package:
    |
    |     php artisan vendor:publish --tag=webx-vacancies-views
    |
    */

    'views' => [
        'index' => env('WEBX_VACANCIES_VIEW_INDEX', 'vacancies.index'),
        'vacancy' => env('WEBX_VACANCIES_VIEW_VACANCY', 'vacancies.vacancy'),
    ],

    /*
    |---------------------------------------------------------------------------
    | The layout the public pages stand in
    |---------------------------------------------------------------------------
    |
    | The name of a Blade component: `'layout'` for the `<x-layout>` a site keeps
    | in `resources/views/components/layout.blade.php`. Empty prints the
    | package's own `webx-vacancies::standalone` — a bare document. Two slots:
    | `head`, and the default one, with `@stack('head')` beside `{{ $head }}`.
    |
    */

    'layout' => env('WEBX_VACANCIES_LAYOUT'),

    /*
    |---------------------------------------------------------------------------
    | The trail above the content
    |---------------------------------------------------------------------------
    |
    | Whether the package's views print the crumbs a reader sees. The
    | BreadcrumbList in the <head> is `module-seo`'s and stays either way.
    |
    */

    'breadcrumbs' => (bool) env('WEBX_VACANCIES_BREADCRUMBS', true),

    /*
    |---------------------------------------------------------------------------
    | What the index runs through
    |---------------------------------------------------------------------------
    */

    'middleware' => ['web', 'webx.locale'],

];
