<?php

declare(strict_types=1);

return [
    // The JSON API of the checkout's payment step (spec §10.2) and the address of its script.
    'api_path' => 'api/payments',
    'script_path' => 'webx/payments',

    // Writes of the buyer's choice per IP a minute.
    'throttle' => [
        'write' => 30,
    ],

    // The journal of the exchange with gateways (spec §20).
    'exchange' => [
        // The connection the journal writes through. Null — a copy of the default connection made
        // at boot, so that an event commits on its own and a rolled-back status change never takes
        // it along (decision 38). An in-memory SQLite database cannot be opened twice, so there
        // the default connection itself is used.
        'connection' => env('WEBX_PAYMENTS_EXCHANGE_CONNECTION'),

        // Bytes kept of one body (decision 50); the rest is cut, the size and sha256 stay whole.
        // The column is a MEDIUMBLOB, so 16 MB is the ceiling.
        'body_limit' => 1024 * 1024,

        // `webx:doctor` warns once the table is bigger than this, in bytes (§20.4). Nothing is
        // ever pruned by itself: `webx:payments:exchange-prune` is the only way (§20.6).
        'warn_size' => 1024 * 1024 * 1024,
    ],

    // Every request a satellite makes to its gateway (§20.3), in seconds.
    'http' => [
        'timeout' => 15,
        'connect_timeout' => 5,
    ],
];
