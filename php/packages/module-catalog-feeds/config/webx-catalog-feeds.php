<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | The prefix of a feed's address
    |--------------------------------------------------------------------------
    |
    | A feed answers at `/{path}/{key}.{ext}` (§7.5 of the feeds spec). Changing it moves every
    | address a marketplace already holds.
    |
    */

    'path' => env('WEBX_CATALOG_FEEDS_PATH', 'feeds'),

];
