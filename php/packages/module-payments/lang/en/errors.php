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
    ],
];
