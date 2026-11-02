<?php

declare(strict_types=1);

return [
    // The JSON API of the delivery step (§12.2) and the address of its script.
    'path' => 'api/delivery',
    'script_path' => 'webx/delivery',

    // Requests a minute per IP: suggestions and points (60, §12.2), and writes of the choice.
    'throttle' => [
        'suggest' => 60,
        'write' => 60,
    ],

    // Points a page of the list in a locality (§12.2).
    'points_per_page' => 20,

    // The price a carrier's API gives (§9.2) — the only place the checkout may wait for a carrier.
    'quotes' => [
        // The same step shown again does not call the API: by method, the carrier's locality and
        // what travels.
        'ttl' => 600,

        // Seconds a satellite gives its API for one price (`Quotes::timeout()`). Late or failed —
        // «By carrier rates», and the method stays in the list; making an order waits for nothing
        // and falls back to nothing.
        'timeout' => 2,
    ],

    'sync' => [
        // A complete pass that would remove more than this share of a carrier's records removes
        // nothing and is held until a person confirms (§7.5): an empty or cut-off answer of an
        // API must not take a network of points away.
        'max_removal_share' => 0.1,

        // A page the carrier failed is tried again from the same cursor, after these pauses.
        'tries' => 3,
        'backoff' => [60, 300, 900],

        // A queued run writes pages for this long, then queues the rest.
        'job_seconds' => 60,

        // A run silent for this long is dead — its worker is gone — and a new one may start.
        'stuck_after_minutes' => 360,
    ],

    // «Carrier A», the fake carrier of the tests, on a development site: its towns, points and
    // streets in the private-use country XX, waybills that go nowhere. For the demo and the guide of
    // a carrier (apps/docs/guide/delivery-carriers.md) — never in production.
    'fake_carrier' => (bool) env('WEBX_DELIVERY_FAKE_CARRIER', false),

    // Per carrier: 'carrier-a' => ['schedule' => ['points' => '0 4 * * *', 'localities' => 'manual']].
    // A cron over the satellite's own, or `manual` — run from the panel, MCP or the command only.
    'carriers' => [],
];
