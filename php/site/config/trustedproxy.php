<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | TLS ends at a proxy in front of the app (the hosting, devbox, Traefik), so
    | the scheme and the visitor's address arrive in its X-Forwarded-* headers.
    | They are believed only from these addresses: anybody else could send the
    | same headers and pass for https or for another IP.
    |
    | By default — loopback and the private networks, which is where the proxy
    | of a hosting stands. A proxy on a public address (a CDN) is named in
    | TRUSTED_PROXIES, comma-separated, and the list replaces this one. `*`
    | believes every address, and `php artisan webx:doctor` warns about it.
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: [
        '127.0.0.0/8',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '::1',
        'fc00::/7',
    ],

];
