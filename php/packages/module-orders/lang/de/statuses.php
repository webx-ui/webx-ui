<?php

declare(strict_types=1);

return [
    'public' => [
        'accepted' => [
            'name' => 'Angenommen',
            'description' => 'Wir haben Ihre Bestellung und melden uns.',
        ],
        'in_progress' => [
            'name' => 'In Bearbeitung',
            'description' => 'Wir stellen Ihre Bestellung zusammen.',
        ],
        'shipped' => [
            'name' => 'Versandt',
            'description' => 'Ihre Bestellung ist unterwegs.',
        ],
        'completed' => [
            'name' => 'Abgeschlossen',
            'description' => 'Die Bestellung ist bei Ihnen. Vielen Dank!',
        ],
        'cancelled' => [
            'name' => 'Storniert',
            'description' => 'Die Bestellung wurde storniert.',
        ],
    ],
    'internal' => [
        'new' => 'Neu',
        'awaiting_payment' => 'Wartet auf Zahlung',
        'taken' => 'In Arbeit genommen',
        'awaiting_call' => 'Wartet auf Anruf',
        'packing' => 'Wird gepackt',
        'handed_to_delivery' => 'An Versand übergeben',
        'completed' => 'Abgeschlossen',
        'cancelled' => 'Storniert',
    ],
];
