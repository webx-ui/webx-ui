<?php

declare(strict_types=1);

// Statuses of attempts, their problems and where they started (spec §8.1, §9.4, §17).
return [
    'statuses' => [
        'created' => 'Utworzona',
        'pending' => 'W toku',
        'authorized' => 'Zablokowana',
        'paid' => 'Opłacona',
        'failed' => 'Nieudana',
        'cancelled' => 'Anulowana',
        'expired' => 'Wygasła',
    ],
    'problems' => [
        'duplicate' => 'Opłacono dwa razy',
        'amount_mismatch' => 'Zła kwota',
        'order_cancelled' => 'Zamówienie anulowane',
        'hold_expiring' => 'Blokada wygasa',
        'capture_failed' => 'Pobranie nie powiodło się',
    ],
    'sources' => [
        'checkout' => 'Kasa',
        'pay-page' => 'Strona płatności',
        'poll' => 'Sprawdzenie w tle',
        'panel' => 'Panel',
        'mcp' => 'Agent',
        'notify' => 'Powiadomienie',
        'return' => 'Powrót',
        'cli' => 'Konsola',
        'api' => 'API',
        'code' => 'Kod',
    ],
];
