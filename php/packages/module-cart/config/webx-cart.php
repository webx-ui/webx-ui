<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | ISO 4217, the cart's one currency. Left empty, the catalogue's
    | (`webx-catalog.price.currency`) is taken when the catalogue is installed;
    | with neither the cart takes no writes and `webx:doctor` says so. A
    | currencies module replaces this by binding its own CurrencySource.
    |
    */

    'currency' => env('WEBX_CART_CURRENCY'),

    /*
    |--------------------------------------------------------------------------
    | API
    |--------------------------------------------------------------------------
    |
    | The prefix of the JSON API (`GET /api/cart`, `POST /api/cart/items`, …).
    | A route, so `routing` never hands the address to a page. Writes are
    | throttled per IP: this many a minute.
    |
    */

    'path' => env('WEBX_CART_PATH', 'api/cart'),

    'throttle' => 60,

    /*
    |--------------------------------------------------------------------------
    | Widget
    |--------------------------------------------------------------------------
    |
    | <x-webx-cart /> without a mode: drawer, dialog or dropdown. The checkout
    | button leads to checkout_url, else to the route named `checkout` when
    | the site has one; with neither there is no button. The widget's script
    | is served at {script_path}/cart.js.
    |
    */

    'widget' => [
        'mode' => env('WEBX_CART_WIDGET', 'drawer'),
    ],

    'checkout_url' => env('WEBX_CART_CHECKOUT_URL'),

    'script_path' => 'webx/cart',

    /*
    |--------------------------------------------------------------------------
    | Cookie
    |--------------------------------------------------------------------------
    |
    | The visitor's token. HttpOnly, SameSite=Lax, extended on every write.
    |
    */

    'cookie' => [
        'name' => env('WEBX_CART_COOKIE', 'webx_cart'),
        'days' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pruning
    |--------------------------------------------------------------------------
    |
    | Carts untouched for this many days are deleted daily by
    | `webx:cart:prune`. Null switches the schedule off.
    |
    */

    'prune_after_days' => 60,

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    |
    | Lines in one batch, units in one line, lines in one cart.
    |
    */

    'batch_max' => 20,

    'qty_max' => 999,

    'lines_max' => 100,

];
