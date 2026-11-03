<?php

declare(strict_types=1);

// Statuses of attempts, their problems and where they started (spec §8.1, §9.4, §17).
return [
    'statuses' => [
        'created' => 'Erstellt',
        'pending' => 'In Bearbeitung',
        'authorized' => 'Reserviert',
        'paid' => 'Bezahlt',
        'failed' => 'Fehlgeschlagen',
        'cancelled' => 'Storniert',
        'expired' => 'Abgelaufen',
    ],
    'problems' => [
        'duplicate' => 'Doppelt bezahlt',
        'amount_mismatch' => 'Falscher Betrag',
        'order_cancelled' => 'Bestellung storniert',
        'hold_expiring' => 'Reservierung läuft ab',
        'capture_failed' => 'Einzug fehlgeschlagen',
    ],
    'sources' => [
        'checkout' => 'Checkout',
        'pay-page' => 'Zahlungsseite',
        'poll' => 'Hintergrundprüfung',
        'panel' => 'Panel',
        'mcp' => 'Agent',
        'notify' => 'Benachrichtigung',
        'return' => 'Rückkehr',
        'cli' => 'Konsole',
        'api' => 'API',
        'code' => 'Code',
    ],
];
