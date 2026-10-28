<?php

declare(strict_types=1);

// One phrase a code of Totals\Warning: `line.gone` is ['line']['gone'].
return [
    'line' => [
        'gone' => ':name n\'est plus disponible. Vous pouvez le retirer.',
        'unavailable' => ':name ne peut pas être acheté pour le moment.',
        'currency' => 'Le prix de :name est dans une autre devise : il ne peut pas être acheté ici.',
        'qty_limited' => 'Seulement :max de :name peuvent être achetés.',
    ],
    'price' => [
        'down' => ':name est maintenant moins cher.',
        'up' => ':name coûte maintenant plus cher.',
    ],
    'tenders' => [
        'capped' => 'Seule une partie de votre solde est utilisée : il dépasse le total.',
    ],
    'totals' => [
        'source_failed' => 'Une partie du total n\'a pas pu être calculée. Réessayez plus tard.',
    ],
];
