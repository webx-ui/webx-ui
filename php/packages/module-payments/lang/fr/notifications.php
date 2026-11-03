<?php

declare(strict_types=1);

// The notices of payments (spec §11.5): the values it adds to the order's events and the manager's
// `payments.problem` with its default template; the site's settings tab that chooses its channels.
return [
    'problem' => 'Problème de paiement',
    'vars' => [
        'payment_url' => 'Lien pour payer la commande',
        'payment_instructions' => 'Coordonnées bancaires pour le paiement',
        'payment_method' => 'Mode de paiement',
        'number' => 'Numéro de commande',
        'order_url' => 'Commande dans le panneau',
        'problem' => 'Ce qui s’est passé',
        'amount' => 'Montant',
        'method' => 'Mode de paiement',
        'gateway_ref' => 'ID du paiement chez la passerelle',
        'message' => 'Détails',
    ],
    'sample' => [
        'subject' => 'Problème de paiement : commande {{number}}',
        'template' => "{{problem}} — commande {{number}}, {{amount}}, {{method}}.\n{{message}}\nID du paiement : {{gateway_ref}}\n{{order_url}}",
    ],
    'settings' => [
        'tab' => 'Paiements',
        'card' => 'Notifications',
        'help' => 'Qui prévenir quand l’argent arrive là où il ne devrait pas : payé deux fois, mauvais montant, argent pour une commande annulée, une préautorisation qui expire, un débit refusé.',
    ],
];
