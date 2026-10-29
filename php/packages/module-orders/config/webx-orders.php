<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Number
    |--------------------------------------------------------------------------
    |
    | The number people see (spec §4): prefix, then start + id, padded with
    | zeros to `pad` digits — 1001, 1002… by default. No counter and no lock:
    | the id is the counter, and a gap a rolled-back order left does not
    | matter to a shop without fiscal numbering. A new format leaves the
    | numbers given out as they are.
    |
    */

    'number' => [
        'prefix' => env('WEBX_ORDERS_NUMBER_PREFIX', ''),
        'start' => (int) env('WEBX_ORDERS_NUMBER_START', 1000),
        'pad' => (int) env('WEBX_ORDERS_NUMBER_PAD', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Initial statuses
    |--------------------------------------------------------------------------
    |
    | The internal status a new order comes into (§5.2), by id, and the one
    | for an order that awaits payment. Null, or a status that is not active:
    | the first of the first public group, and for an order awaiting payment
    | the one after it — «New» and «Awaiting payment» of the set a site
    | starts with.
    |
    */

    'initial_status' => null,

    'initial_status_awaiting_payment' => null,

];
