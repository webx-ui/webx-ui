<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | The most a collection shows
    |--------------------------------------------------------------------------
    |
    | The ceiling of every collection (decision 11 of the spec). Each collection has a limit of its
    | own, from 1 up to this; a value above it is refused when the limit is saved.
    |
    */

    'max_limit' => (int) env('WEBX_CATALOG_COLLECTIONS_MAX_LIMIT', 48),

];
