<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Links between the catalogue's pages
    |--------------------------------------------------------------------------
    |
    | How many links each of the four lists prints (§7 of the landings spec): «Collections» on a
    | category, the neighbours of a landing on its base, the same set on other categories, and
    | «In collections» beside a product. 0 turns a list off.
    |
    */

    'links' => [
        'category' => (int) env('WEBX_CATALOG_LANDINGS_LINKS_CATEGORY', 12),
        'siblings' => (int) env('WEBX_CATALOG_LANDINGS_LINKS_SIBLINGS', 12),
        'elsewhere' => (int) env('WEBX_CATALOG_LANDINGS_LINKS_ELSEWHERE', 8),
        'product' => (int) env('WEBX_CATALOG_LANDINGS_LINKS_PRODUCT', 8),
    ],

    /*
    |--------------------------------------------------------------------------
    | Recommended products
    |--------------------------------------------------------------------------
    |
    | The most the strip over a landing's list shows; the rest of the hand-picked ones wait.
    |
    */

    'recommended' => (int) env('WEBX_CATALOG_LANDINGS_RECOMMENDED', 8),

];
