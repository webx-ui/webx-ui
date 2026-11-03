<?php

declare(strict_types=1);

// Refusals of the service, the API and the agent's tools (spec §17).
return [
    'mark' => [
        'paid' => 'Die Bestellung ist bereits bezahlt',
        'cancelled' => 'Die Bestellung ist storniert',
    ],
    'begin' => [
        'cancelled' => 'Die Bestellung ist storniert',
        'nothing_due' => 'Für die Bestellung ist nichts zu zahlen',
        'not_online' => 'Die Zahlungsart ist keine Online-Zahlung',
    ],
];
