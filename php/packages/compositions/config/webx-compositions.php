<?php

declare(strict_types=1);

return [
    /*
    | Patches above the local theme (COMPOSITIONS §6.3): the shape of a JSON patch, as PHP arrays,
    | by composition and handle. The usual place for a site's patches is theme/compositions/; this
    | one is for a site without a theme and for what differs between environments.
    |
    |     'layout' => ['default' => ['attrs' => ['html' => ['data-webx-smooth-scroll' => true]]]],
    */
    'patches' => [],

    /*
    | Which layout a page type stands in, when its module has no key of its own (§4): a handle
    | to a composition, `['catalog.checkout' => 'layout.minimal']`. Decided when compiling.
    */
    'layouts' => [],

    /*
    | Where the compiled views and their manifest are written.
    */
    'compiled' => env('WEBX_COMPOSITIONS_COMPILED', storage_path('framework/compositions')),

    /*
    | Whether a request compares the sources with the manifest and compiles again what changed
    | (§6.2). Null follows Blade: `view.check_cache_timestamps`.
    */
    'check' => null,
];
