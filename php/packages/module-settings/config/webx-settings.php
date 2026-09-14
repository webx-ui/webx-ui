<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Cache
    |---------------------------------------------------------------------------
    |
    | The site reads settings on every request, so the whole table is kept in
    | the cache as one entry and thrown away when anything is saved.
    |
    */

    'cache' => [
        'enabled' => env('WEBX_SETTINGS_CACHE', true),
        'ttl' => 86400,
        'key' => 'webx.settings',
    ],

];
