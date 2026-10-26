<?php

declare(strict_types=1);

return [
    /*
    | The currency a new configurator starts with — an ISO 4217 code. Each configurator keeps its
    | own in its schema; this is only what the blank one is filled in with.
    */
    'currency' => env('WEBX_CONFIGURATOR_CURRENCY', 'USD'),

    /*
    | Where the widget's script is served from (§12.4): a path of the site, not a page.
    */
    'path' => 'webx/configurator',
];
