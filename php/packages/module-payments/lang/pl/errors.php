<?php

declare(strict_types=1);

// Refusals of the service, the API and the agent's tools (spec §17).
return [
    'mark' => [
        'paid' => 'Zamówienie jest już opłacone',
        'cancelled' => 'Zamówienie jest anulowane',
    ],
    'begin' => [
        'cancelled' => 'Zamówienie jest anulowane',
        'nothing_due' => 'Za zamówienie nie ma nic do zapłaty',
        'not_online' => 'Metoda nie jest płatnością online',
    ],
];
