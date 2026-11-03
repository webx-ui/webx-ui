<?php

declare(strict_types=1);

// The built-in payment methods (spec §12, §17): their default titles, notes and settings.
return [
    'on_receipt' => [
        'title' => 'Zahlung bei Erhalt',
        'pickup' => 'Zahlung an der Kasse',
        'cod_note' => 'Der Zustelldienst berechnet für die Nachnahme eine eigene Gebühr',
        'settings' => [
            'title_pickup' => 'Bezeichnung bei Abholung im Geschäft',
            'cod_note' => 'Hinweis zur Nachnahmegebühr',
        ],
    ],
    'bank_transfer' => [
        'title' => 'Banküberweisung',
        'purpose' => 'Zahlung für Bestellung {number}',
        'settings' => [
            'recipient' => 'Empfänger',
            'account' => 'Konto und Bank',
            'purpose' => 'Verwendungszweck ({number} — Bestellnummer)',
            'note' => 'Zahlungsfrist',
        ],
    ],
];
