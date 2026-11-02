<?php

declare(strict_types=1);

return [
    'carrier' => [
        'title' => 'Nova Poshta',
    ],
    'types' => [
        'branch' => [
            'title' => 'Agence Nova Poshta',
            'description' => 'Retrait dans une agence du transporteur ; vous payez la livraison au transporteur au retrait.',
        ],
        'locker' => [
            'title' => 'Consigne Nova Poshta',
            'description' => 'Retrait dans une consigne du transporteur ; vous payez la livraison au transporteur au retrait.',
        ],
        'courier' => [
            'title' => 'Coursier Nova Poshta',
            'description' => 'Le transporteur livre le colis à votre porte ; vous payez la livraison au transporteur à la réception.',
        ],
    ],
    'settings' => [
        'payment_method' => 'Paiement de la livraison',
        'payment_methods' => [
            'Cash' => 'Espèces',
            'NonCash' => 'Virement (sous contrat avec le transporteur)',
        ],
        'cargo_type' => 'Type de fret par défaut',
        'cargo_types' => [
            'Parcel' => 'Colis',
            'Cargo' => 'Fret',
            'Documents' => 'Documents',
        ],
        'weight' => 'Poids par défaut, kg',
        'description' => 'Description du contenu par défaut',
        'description_default' => 'Marchandises',
    ],
    'credentials' => [
        'api_key' => 'Clé API',
    ],
    'connection' => [
        'ok' => 'Expéditeurs dans le compte : :count',
        'key_refused' => 'Le transporteur n\'a pas accepté la clé API.',
        'no_answer' => 'Le transporteur n\'a pas répondu.',
        'no_sender' => 'Le compte du transporteur n\'a aucun expéditeur.',
        'no_key' => 'Saisissez d\'abord la clé API.',
    ],
];
