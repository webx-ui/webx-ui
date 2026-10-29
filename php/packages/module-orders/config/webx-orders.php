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

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | The channels of webx-ui/module-notifications each milestone's event goes
    | to (§10.1), by id — read when the settings of the section never saved a
    | list. A public status keeps its own channels. Without the notifications
    | module these are not read at all.
    |
    */

    'notify' => [
        'placed' => [],
        'paid' => [],
        'payment_failed' => [],
        'received' => [],
        'cancelled' => [],
        'refunded' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | The buyer's page of an order
    |--------------------------------------------------------------------------
    |
    | `/order/{number}` behind a signed link (§13.1): the layout it stands in —
    | `'layout'` for the site's `<x-layout>`, empty for the package's own bare
    | document — and the middleware of the page. «My orders» of the account
    | stands in the account's frame of `webx-ui/module-users` instead.
    |
    */

    'layout' => env('WEBX_ORDERS_LAYOUT'),

    'middleware' => ['web', 'webx.locale'],

    /** Orders a page of «My orders» shows. */
    'account_per_page' => 20,

];
