<?php

declare(strict_types=1);

return [
    'public' => [
        'accepted' => [
            'name' => 'Accepted',
            'description' => 'We have your order and will be in touch.',
        ],
        'in_progress' => [
            'name' => 'In progress',
            'description' => 'We are putting your order together.',
        ],
        'shipped' => [
            'name' => 'Shipped',
            'description' => 'Your order is on its way.',
        ],
        'completed' => [
            'name' => 'Completed',
            'description' => 'The order is with you. Thank you!',
        ],
        'cancelled' => [
            'name' => 'Cancelled',
            'description' => 'The order was cancelled.',
        ],
    ],
    'internal' => [
        'new' => 'New',
        'awaiting_payment' => 'Awaiting payment',
        'taken' => 'Taken into work',
        'awaiting_call' => 'Waiting for a call',
        'packing' => 'Being packed',
        'handed_to_delivery' => 'Handed to delivery',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],
];
