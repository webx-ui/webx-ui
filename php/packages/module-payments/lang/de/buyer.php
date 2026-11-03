<?php

declare(strict_types=1);

// What the buyer reads (spec §10, §12, §17): the payment step, the bank details, the order's line.
return [
    'step' => [
        'methods' => 'Zahlungsart',
        'save' => 'Speichern',
        'delivery_first' => 'Wählen Sie zuerst den Versand — davon hängen die Zahlungsarten ab',
        'method_dropped' => 'Die gewählte Zahlungsart ist für diesen Versand nicht verfügbar. Bitte wählen Sie eine andere.',
    ],
    'errors' => [
        'choose_method' => 'Wählen Sie eine Zahlungsart',
        'method_gone' => 'Die Zahlungsart ist nicht mehr verfügbar, bitte wählen Sie eine andere',
    ],
    'instructions' => [
        'title' => 'Bankverbindung für die Zahlung',
        'recipient' => 'Empfänger',
        'account' => 'Konto',
        'purpose' => 'Verwendungszweck',
        'note' => 'Frist',
    ],
    'status' => [
        'paid' => 'Bezahlt',
        'awaiting' => 'Wartet auf Zahlung',
        'pay' => 'Bezahlen',
    ],
    'pay' => [
        'title' => 'Zahlung für Bestellung :number',
        'description' => 'Bestellung :number',
        'due' => 'Zu zahlen: :amount',
        'paid' => 'Bestellung :number ist bezahlt',
        'cancelled' => 'Bestellung :number ist storniert',
        'checking' => 'Wir prüfen die Zahlung. Das dauert meist bis zu einer Minute.',
        'failed' => 'Die Zahlung ist nicht durchgegangen',
        'unavailable' => 'Die Zahlung konnte nicht geöffnet werden. Versuchen Sie es erneut oder wählen Sie eine andere Zahlungsart.',
        'on_receipt' => 'Die Bestellung wird bei Erhalt bezahlt',
        'order_link' => 'Zur Bestellung',
        'redirecting' => 'Weiter zur Zahlungsseite…',
        'go' => 'Zur Zahlung',
    ],
];
