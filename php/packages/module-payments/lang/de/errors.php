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
        'paid' => 'Die Bestellung ist bereits bezahlt',
        'held' => 'Das Geld für die Bestellung ist reserviert: der Shop bucht es ab',
    ],
    'hold' => [
        'not_held' => 'Mit dieser Zahlung ist kein Geld reserviert',
        'no_hold' => 'Diese Zahlungsart kann kein Geld reservieren',
        'amount' => 'Der Betrag muss größer als null sein',
        'too_much' => 'Mehr als reserviert',
        'partial' => 'Dieses Gateway bucht nur den ganzen reservierten Betrag ab',
        'busy' => 'Die Zahlung wird gerade aktualisiert, bitte in einer Minute erneut versuchen',
    ],
    'problem' => [
        'none' => 'Die Zahlung hat kein Problem',
        'hold' => 'Eine Reservierung wird durch Abbuchen oder Freigeben gelöst',
    ],
];
