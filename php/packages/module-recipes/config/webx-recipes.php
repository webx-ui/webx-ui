<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | The first segment of every recipes address
    |---------------------------------------------------------------------------
    |
    | A category is at `recipes/breakfasts`, a recipe at `recipes/oat-porridge` —
    | on one level, so a recipe in three categories still has one address. Their
    | slugs share that level too: a category and a recipe that want the same slug
    | are refused, the second of them under its own field.
    |
    | Never empty. Recipes in the root of the site would argue with the tree of
    | pages over every address, and the package refuses to boot rather than let
    | them.
    |
    | Changing this afterwards is `php artisan webx:routes:rebuild --type=recipe
    | --type=recipe-category`: the paths are recomputed and the old ones stay
    | behind as aliases that redirect.
    |
    */

    'prefix' => env('WEBX_RECIPES_PREFIX', 'recipes'),

    /*
    |---------------------------------------------------------------------------
    | The index page
    |---------------------------------------------------------------------------
    |
    | On, the package answers the prefix itself with the catalogue. Off, the route
    | is not registered and the address is free: a page with the slug `recipes`
    | takes it, and puts the catalogue on itself as a block — with whatever else
    | the site wants around it. Categories and recipes keep their addresses under
    | the prefix either way, and their trail goes through whatever stands there.
    |
    */

    'index' => (bool) env('WEBX_RECIPES_INDEX', true),

    /*
    |---------------------------------------------------------------------------
    | Recipes on one page of the catalogue
    |---------------------------------------------------------------------------
    |
    | The index and a category page. A catalogue put on a page as a block has its
    | own number, in the block.
    |
    */

    'per-page' => (int) env('WEBX_RECIPES_PER_PAGE', 24),

    /*
    |---------------------------------------------------------------------------
    | Similar recipes under a recipe
    |---------------------------------------------------------------------------
    |
    | The most a recipe page shows. The ones an editor chose come first and are
    | never topped up; with none chosen, the package picks by what the recipes
    | share — a category or a service counts two, a nutrient one.
    |
    */

    'similar' => (int) env('WEBX_RECIPES_SIMILAR', 3),

    /*
    |---------------------------------------------------------------------------
    | The views the public half is printed with
    |---------------------------------------------------------------------------
    |
    | A site keeps its own markup here. Until it has written these views, the
    | package's own are used. A recipe page is one view of parts, each its own
    | `@include`, so a site can publish and rewrite one part — the facts, the
    | nutrition table — and leave the rest to the package:
    |
    |     php artisan vendor:publish --tag=webx-recipes-views
    |
    | The catalogue itself (`partials/catalog`) is shared by the index, a category
    | page and the block: rewrite it once and all three change.
    |
    */

    'views' => [
        'index' => env('WEBX_RECIPES_VIEW_INDEX', 'recipes.index'),
        'category' => env('WEBX_RECIPES_VIEW_CATEGORY', 'recipes.category'),
        'recipe' => env('WEBX_RECIPES_VIEW_RECIPE', 'recipes.recipe'),
    ],

    /*
    |---------------------------------------------------------------------------
    | The layout the public pages stand in
    |---------------------------------------------------------------------------
    |
    | The name of a Blade component: `'layout'` for the `<x-layout>` a site keeps
    | in `resources/views/components/layout.blade.php`. Empty prints the package's
    | own `webx-recipes::standalone` — a bare document. Two slots: `head`, and the
    | default one, with `@stack('head')` beside `{{ $head }}`.
    |
    */

    'layout' => env('WEBX_RECIPES_LAYOUT'),

    /*
    |---------------------------------------------------------------------------
    | The trail above the content
    |---------------------------------------------------------------------------
    |
    | Whether the package's views print the crumbs a reader sees. The
    | BreadcrumbList in the <head> is `module-seo`'s and stays either way.
    |
    */

    'breadcrumbs' => (bool) env('WEBX_RECIPES_BREADCRUMBS', true),

    /*
    |---------------------------------------------------------------------------
    | What the index route runs through
    |---------------------------------------------------------------------------
    */

    'middleware' => ['web', 'webx.locale'],

];
