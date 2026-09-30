<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Where the brands live
    |--------------------------------------------------------------------------
    |
    | The list is `/{prefix}/`, a brand's page `/{prefix}/{slug}/`, with the filter's tail behind it:
    | `/brands/apple/category_laptops/`. Changing it on a live site moves every brand — run
    | `php artisan webx:routes:rebuild --type=catalog.brand`, which leaves the old addresses as
    | redirects.
    |
    */

    'prefix' => env('WEBX_CATALOG_BRANDS_PREFIX', 'brands'),

];
