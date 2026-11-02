<?php

declare(strict_types=1);

return [
    'carrier' => [
        'title' => 'Nova Poshta',
    ],
    'types' => [
        'branch' => [
            'title' => 'Filiale Nova Poshta',
            'description' => 'Ritiro in una filiale del corriere; paghi la spedizione al corriere al ritiro.',
        ],
        'locker' => [
            'title' => 'Armadietto Nova Poshta',
            'description' => 'Ritiro da un armadietto del corriere; paghi la spedizione al corriere al ritiro.',
        ],
        'courier' => [
            'title' => 'Corriere Nova Poshta',
            'description' => 'Il corriere consegna il pacco alla tua porta; paghi la spedizione al corriere alla consegna.',
        ],
    ],
    'settings' => [
        'payment_method' => 'Pagamento della spedizione',
        'payment_methods' => [
            'Cash' => 'Contanti',
            'NonCash' => 'Senza contanti (con contratto con il corriere)',
        ],
        'cargo_type' => 'Tipo di carico predefinito',
        'cargo_types' => [
            'Parcel' => 'Pacco',
            'Cargo' => 'Carico',
            'Documents' => 'Documenti',
        ],
        'weight' => 'Peso predefinito, kg',
        'description' => 'Descrizione del contenuto predefinita',
        'description_default' => 'Merci',
    ],
    'credentials' => [
        'api_key' => 'Chiave API',
    ],
    'connection' => [
        'ok' => 'Mittenti nell\'account: :count',
        'key_refused' => 'Il corriere non ha accettato la chiave API.',
        'no_answer' => 'Il corriere non ha risposto.',
        'no_sender' => 'L\'account del corriere non ha alcun mittente.',
        'no_key' => 'Inserisci prima la chiave API.',
    ],
];
