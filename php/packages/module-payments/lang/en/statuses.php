<?php

declare(strict_types=1);

// Statuses of attempts, their problems and where they started (spec §8.1, §9.4, §17).
return [
    'statuses' => [
        'created' => 'Created',
        'pending' => 'In progress',
        'authorized' => 'Held',
        'paid' => 'Paid',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
        'expired' => 'Expired',
    ],
    'problems' => [
        'duplicate' => 'Paid twice',
        'amount_mismatch' => 'Wrong amount',
        'order_cancelled' => 'Order cancelled',
        'hold_expiring' => 'Hold expires',
        'capture_failed' => 'Capture failed',
    ],
    'sources' => [
        'checkout' => 'Checkout',
        'pay-page' => 'Payment page',
        'poll' => 'Background check',
        'panel' => 'Panel',
        'mcp' => 'Agent',
        'notify' => 'Notification',
        'return' => 'Return',
        'cli' => 'Console',
        'api' => 'API',
        'code' => 'Code',
    ],
];
