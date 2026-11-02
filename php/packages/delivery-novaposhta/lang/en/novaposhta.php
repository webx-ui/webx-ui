<?php

declare(strict_types=1);

return [
    'carrier' => [
        'title' => 'Nova Poshta',
    ],
    'types' => [
        'branch' => [
            'title' => 'Nova Poshta branch',
            'description' => 'Pick up at a branch of the carrier; you pay the carrier on receipt.',
        ],
        'locker' => [
            'title' => 'Nova Poshta parcel locker',
            'description' => 'Pick up from a parcel locker of the carrier; you pay the carrier on receipt.',
        ],
        'courier' => [
            'title' => 'Nova Poshta courier',
            'description' => 'The carrier brings the parcel to your door; you pay the carrier on receipt.',
        ],
    ],
    'settings' => [
        'payment_method' => 'Payment for delivery',
        'payment_methods' => [
            'Cash' => 'Cash',
            'NonCash' => 'Non-cash (under a contract with the carrier)',
        ],
        'cargo_type' => 'Cargo type by default',
        'cargo_types' => [
            'Parcel' => 'Parcel',
            'Cargo' => 'Cargo',
            'Documents' => 'Documents',
        ],
        'weight' => 'Weight by default, kg',
        'description' => 'Description of the cargo by default',
        'description_default' => 'Goods',
    ],
    'credentials' => [
        'api_key' => 'API key',
    ],
    'connection' => [
        'ok' => 'Senders in the account: :count',
        'key_refused' => 'The carrier did not accept the API key.',
        'no_answer' => 'The carrier did not answer.',
        'no_sender' => 'The carrier\'s account has no sender.',
        'no_key' => 'Enter the API key first.',
    ],
];
