<?php

declare(strict_types=1);

return [
    // The groups of the buyer's list (groups spec §2.1): the key, for good, and in the order of the
    // array. `title` is a dictionary's key or the words themselves, `pictogram` a name of
    // `MethodGroups::PICTOGRAMS`. A site's `groups` replaces this list whole; an empty one is a flat
    // list. The panel renames and reorders what is here, and neither adds nor removes.
    'groups' => [
        'on_receipt' => ['title' => 'webx-payments::groups.on_receipt', 'pictogram' => 'cash'],
        'online' => ['title' => 'webx-payments::groups.online', 'pictogram' => 'card'],
        'transfer' => ['title' => 'webx-payments::groups.transfer', 'pictogram' => 'document'],
    ],

    // The buyer's page of payment of an order and the step to the gateway (spec §4): /pay/{number},
    // /pay/go/{uuid}.
    'path' => 'pay',

    // The gateways' notifications (spec §9.2): POST /{path}/{method}. A body over max_body is
    // answered 413 — and still journaled, cut (§20.4).
    'notify' => [
        'path' => 'payments/notify',
        'max_body' => 1024 * 1024,
    ],

    // «Gateway A» (spec §21): the fake gateway with its three types and its page /pay/fake/{uuid},
    // for development sites and tests. Never in production, whatever this says.
    'fake_gateway' => (bool) env('WEBX_PAYMENTS_FAKE_GATEWAY', false),

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

    // The channels of `webx-ui/module-notifications` a payment problem goes to (§11.5), by id. A
    // site with `module-settings` chooses them on the settings tab «Payments»
    // (`payments.notify.problem`); this list is read only where nothing was saved there.
    'notices' => [
        'problem' => [],
    ],
];
