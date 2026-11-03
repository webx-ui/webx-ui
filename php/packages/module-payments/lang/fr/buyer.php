<?php

declare(strict_types=1);

// What the buyer reads (spec §10, §12, §17): the payment step, the bank details, the order's line.
return [
    'step' => [
        'methods' => 'Mode de paiement',
        'save' => 'Enregistrer',
        'delivery_first' => 'Choisissez d\'abord la livraison — les modes de paiement en dépendent',
        'method_dropped' => 'Le mode de paiement choisi n\'est pas disponible pour cette livraison. Choisissez-en un autre.',
    ],
    'errors' => [
        'choose_method' => 'Choisissez un mode de paiement',
        'method_gone' => 'Ce mode de paiement n\'est plus disponible, choisissez-en un autre',
    ],
    'instructions' => [
        'title' => 'Coordonnées bancaires pour le paiement',
        'recipient' => 'Bénéficiaire',
        'account' => 'Compte',
        'purpose' => 'Motif du paiement',
        'note' => 'Délai',
    ],
    'status' => [
        'paid' => 'Payée',
        'awaiting' => 'En attente de paiement',
        'pay' => 'Payer',
    ],
];
