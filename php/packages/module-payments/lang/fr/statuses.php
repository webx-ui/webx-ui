<?php

declare(strict_types=1);

// Statuses of attempts, their problems and where they started (spec §8.1, §9.4, §17).
return [
    'statuses' => [
        'created' => 'Créée',
        'pending' => 'En cours',
        'authorized' => 'Bloquée',
        'paid' => 'Payée',
        'failed' => 'Échouée',
        'cancelled' => 'Annulée',
        'expired' => 'Expirée',
    ],
    'problems' => [
        'duplicate' => 'Payée deux fois',
        'amount_mismatch' => 'Mauvais montant',
        'order_cancelled' => 'Commande annulée',
        'hold_expiring' => 'Le blocage expire',
        'capture_failed' => 'Encaissement échoué',
    ],
    'sources' => [
        'checkout' => 'Commande',
        'pay-page' => 'Page de paiement',
        'poll' => 'Vérification en arrière-plan',
        'panel' => 'Panneau',
        'mcp' => 'Agent',
        'notify' => 'Notification',
        'return' => 'Retour',
        'cli' => 'Console',
        'api' => 'API',
        'code' => 'Code',
    ],
];
