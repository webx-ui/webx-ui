<?php

declare(strict_types=1);

// The built-in payment methods (spec §12, §17): their default titles, notes and settings.
return [
    'on_receipt' => [
        'title' => 'Paiement à la réception',
        'pickup' => 'Paiement en caisse',
        'cod_note' => 'Le transporteur facture ses propres frais pour le paiement à la livraison',
        'settings' => [
            'title_pickup' => 'Libellé pour le retrait en magasin',
            'cod_note' => 'Mention sur les frais de paiement à la livraison',
        ],
    ],
    'bank_transfer' => [
        'title' => 'Virement bancaire',
        'purpose' => 'Paiement de la commande {number}',
        'settings' => [
            'recipient' => 'Bénéficiaire',
            'account' => 'Compte et banque',
            'purpose' => 'Motif du paiement ({number} — numéro de commande)',
            'note' => 'Délai de paiement',
        ],
    ],
];
