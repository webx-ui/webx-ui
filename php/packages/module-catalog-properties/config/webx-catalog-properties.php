<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Facets of properties where no category decides them
    |--------------------------------------------------------------------------
    |
    | On a category's page the facets are its set. In the search, on a brand's page and at the
    | root they are picked by coverage: the share of what the page found that has a value of the
    | property. The first `limit` with a share of at least `min_share` stand open; the rest with any
    | share at all go under «More filters». A chosen facet is always open.
    |
    | 8 is, with the core's and the dictionaries' facets, eleven to thirteen blocks — two screens of
    | the column; 10 % drops the properties of a couple of stray products in a mixed result and
    | keeps the main property on the page of a brand of many categories.
    |
    */

    'dynamic_facets' => [
        'min_share' => (float) env('WEBX_CATALOG_PROPERTIES_MIN_SHARE', 0.1),
        'limit' => (int) env('WEBX_CATALOG_PROPERTIES_LIMIT', 8),
    ],

];
