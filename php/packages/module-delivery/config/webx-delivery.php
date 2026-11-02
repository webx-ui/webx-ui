<?php

declare(strict_types=1);

return [
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

    // Per carrier: 'carrier-a' => ['schedule' => ['points' => '0 4 * * *', 'localities' => 'manual']].
    // A cron over the satellite's own, or `manual` — run from the panel, MCP or the command only.
    'carriers' => [],
];
