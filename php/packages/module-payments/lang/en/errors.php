<?php

declare(strict_types=1);

// Refusals of the service, the API and the agent's tools (spec §17).
return [
    'mark' => [
        'paid' => 'The order is paid already',
        'cancelled' => 'The order is cancelled',
    ],
    'begin' => [
        'cancelled' => 'The order is cancelled',
        'nothing_due' => 'The order owes nothing',
        'not_online' => 'The method is not an online one',
        'paid' => 'The order is paid already',
        'held' => 'The money for the order is held: the shop will capture it',
    ],
    'hold' => [
        'not_held' => 'No money is held by this payment',
        'no_hold' => 'This payment method cannot hold money',
        'amount' => 'The amount must be above zero',
        'too_much' => 'More than is held',
        'partial' => 'This gateway captures only the whole amount held',
        'busy' => 'The payment is being updated, try again in a minute',
    ],
    'problem' => [
        'none' => 'The payment has no problem',
        'hold' => 'A hold is resolved by capturing it or letting it go',
    ],
];
