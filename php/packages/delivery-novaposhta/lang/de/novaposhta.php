<?php

declare(strict_types=1);

return [
    'carrier' => [
        'title' => 'Nova Poshta',
    ],
    'types' => [
        'branch' => [
            'title' => 'Nova Poshta Filiale',
            'description' => 'Abholung in einer Filiale des Versanddienstes; die Lieferung zahlen Sie bei Abholung.',
        ],
        'locker' => [
            'title' => 'Nova Poshta Paketautomat',
            'description' => 'Abholung an einem Paketautomaten des Versanddienstes; die Lieferung zahlen Sie bei Abholung.',
        ],
        'courier' => [
            'title' => 'Nova Poshta Kurier',
            'description' => 'Der Versanddienst bringt das Paket an Ihre Tür; die Lieferung zahlen Sie bei Empfang.',
        ],
    ],
    'settings' => [
        'payment_method' => 'Zahlung der Lieferung',
        'payment_methods' => [
            'Cash' => 'Bar',
            'NonCash' => 'Unbar (mit Vertrag beim Versanddienst)',
        ],
        'cargo_type' => 'Standard-Frachtart',
        'cargo_types' => [
            'Parcel' => 'Paket',
            'Cargo' => 'Fracht',
            'Documents' => 'Dokumente',
        ],
        'weight' => 'Standardgewicht, kg',
        'description' => 'Standardbeschreibung der Sendung',
        'description_default' => 'Waren',
    ],
    'credentials' => [
        'api_key' => 'API-Schlüssel',
    ],
    'connection' => [
        'ok' => 'Absender im Konto: :count',
        'key_refused' => 'Der Versanddienst hat den API-Schlüssel nicht akzeptiert.',
        'no_answer' => 'Der Versanddienst hat nicht geantwortet.',
        'no_sender' => 'Im Konto des Versanddienstes gibt es keinen Absender.',
        'no_key' => 'Geben Sie zuerst den API-Schlüssel ein.',
    ],
];
