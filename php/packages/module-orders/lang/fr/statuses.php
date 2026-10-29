<?php

declare(strict_types=1);

return [
    'public' => [
        'accepted' => [
            'name' => 'Acceptée',
            'description' => 'Nous avons votre commande et revenons vers vous.',
        ],
        'in_progress' => [
            'name' => 'En cours',
            'description' => 'Nous préparons votre commande.',
        ],
        'shipped' => [
            'name' => 'Expédiée',
            'description' => 'Votre commande est en route.',
        ],
        'completed' => [
            'name' => 'Terminée',
            'description' => 'La commande est chez vous. Merci !',
        ],
        'cancelled' => [
            'name' => 'Annulée',
            'description' => 'La commande a été annulée.',
        ],
    ],
    'internal' => [
        'new' => 'Nouvelle',
        'awaiting_payment' => 'En attente de paiement',
        'taken' => 'Prise en charge',
        'awaiting_call' => 'Attend un appel',
        'packing' => 'En préparation',
        'handed_to_delivery' => 'Remise au transporteur',
        'completed' => 'Terminée',
        'cancelled' => 'Annulée',
    ],
];
