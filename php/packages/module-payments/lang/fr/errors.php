<?php

declare(strict_types=1);

// Refusals of the service, the API and the agent's tools (spec §17).
return [
    'mark' => [
        'paid' => 'La commande est déjà payée',
        'cancelled' => 'La commande est annulée',
    ],
    'begin' => [
        'cancelled' => 'La commande est annulée',
        'nothing_due' => 'Rien n’est dû pour la commande',
        'not_online' => 'Ce moyen n’est pas un paiement en ligne',
        'paid' => 'La commande est déjà payée',
        'held' => 'L’argent de la commande est bloqué : la boutique le prélèvera',
    ],
    'hold' => [
        'not_held' => 'Aucun argent n’est bloqué pour ce paiement',
        'no_hold' => 'Ce mode de paiement ne peut pas bloquer d’argent',
        'amount' => 'Le montant doit être supérieur à zéro',
        'too_much' => 'Plus que le montant bloqué',
        'partial' => 'Cette passerelle ne prélève que la totalité du montant bloqué',
        'busy' => 'Le paiement est en cours de mise à jour, réessayez dans une minute',
    ],
    'problem' => [
        'none' => 'Le paiement n’a pas de problème',
        'hold' => 'Un blocage se règle en le prélevant ou en le libérant',
    ],
];
