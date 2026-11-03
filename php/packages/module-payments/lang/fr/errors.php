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
    ],
];
