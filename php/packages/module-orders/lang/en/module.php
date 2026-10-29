<?php

declare(strict_types=1);

return [
    'orders' => 'Orders',
    'description' => 'Orders placed on the site: what was bought, by whom, and where each one stands.',
    'empty' => 'No orders yet.',
    'empty-help' => 'An order appears here as soon as it is placed on the site.',
    'order' => 'Order',
    'fields' => [
        'customer_first_name' => 'First name',
        'customer_last_name' => 'Last name',
        'customer_phone' => 'Phone',
        'customer_email' => 'E-mail',
        'recipient' => 'Recipient',
        'comment' => 'Buyer\'s comment',
        'manager_note' => 'Manager note',
    ],
    'milestones' => [
        'placed' => 'Placed',
        'awaiting_payment' => 'Awaiting payment',
        'paid' => 'Paid',
        'received' => 'Received',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
    ],
    'colors' => [
        'neutral' => 'Neutral',
        'info' => 'Info',
        'success' => 'Success',
        'warning' => 'Warning',
        'danger' => 'Danger',
        'accent' => 'Accent',
    ],
];
