<?php

declare(strict_types=1);

return [
    'marks' => [
        'paid' => 'Bezahlt',
        'received' => 'Erhalten',
        'cancelled' => 'Storniert',
        'refunded' => 'Erstattet :amount',
    ],
    'blocks' => [
        'delivery' => 'Lieferung',
        'payment' => 'Zahlung',
    ],
    'order' => [
        'title' => 'Bestellung :number',
        'placed' => 'Aufgegeben am :date',
        'scale' => 'Wo die Bestellung steht',
        'awaiting_payment' => 'Die Bestellung wartet auf Zahlung.',
        'items' => 'Artikel',
        'subtotal' => 'Artikel',
        'total' => 'Gesamt',
        'tenders' => 'Bezahlt mit Guthaben und Gutscheinen',
        'payable' => 'Zu zahlen',
        'contacts' => 'Kontakte',
        'customer' => 'Käufer',
        'recipient' => 'Empfänger',
        'comment' => 'Kommentar',
    ],
    'account' => [
        'menu' => 'Meine Bestellungen',
        'title' => 'Meine Bestellungen',
        'empty' => 'Noch keine Bestellungen. Hier erscheinen Bestellungen, die Sie angemeldet aufgeben.',
        'back' => '← Alle Bestellungen',
        'lines' => 'Positionen: :count',
        'pages' => 'Seiten',
        'newer' => '← Neuere',
        'older' => 'Ältere →',
        'deletion' => 'Laufende Bestellungen (:numbers) werden ausgeführt, aber Sie sehen sie nicht mehr im Konto.',
    ],
];
