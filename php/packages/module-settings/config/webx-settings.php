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

    /*
    |---------------------------------------------------------------------------
    | Stand-own keys
    |---------------------------------------------------------------------------
    |
    | Keys whose value belongs to the stand rather than to the site: a restore
    | of `webx:snapshot` keeps this stand's value and drops the archive's. None
    | ship today — keys and passwords live in .env — but a project that stores
    | an integration's key as a setting names it here.
    |
    */

    'stand_own' => [],

];
