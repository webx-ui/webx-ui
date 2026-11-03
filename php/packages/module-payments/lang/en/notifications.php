<?php

declare(strict_types=1);

// The notices of payments (spec §11.5): the values it adds to the order's events and the manager's
// `payments.problem` with its default template; the site's settings tab that chooses its channels.
return [
    'problem' => 'Payment problem',
    'vars' => [
        'payment_url' => 'Link to pay the order',
        'payment_instructions' => 'Bank details for the payment',
        'payment_method' => 'Payment method',
        'number' => 'Order number',
        'order_url' => 'Order in the panel',
        'problem' => 'What went wrong',
        'amount' => 'Amount',
        'method' => 'Payment method',
        'gateway_ref' => 'Payment ID at the gateway',
        'message' => 'Details',
    ],
    'sample' => [
        'subject' => 'Payment problem: order {{number}}',
        'template' => "{{problem}} — order {{number}}, {{amount}}, {{method}}.\n{{message}}\nPayment ID: {{gateway_ref}}\n{{order_url}}",
    ],
    'settings' => [
        'tab' => 'Payments',
        'card' => 'Notifications',
        'help' => 'Who is told when money comes where it should not: paid twice, a wrong amount, money for a cancelled order, a hold about to end, a capture refused.',
    ],
];
