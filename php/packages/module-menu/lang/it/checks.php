<?php

declare(strict_types=1);

return [
    'menu' => [
        'broken' => [
            'title' => 'Voci di menu che portano a un errore',
            'found' => 'Una voce di menu porta a una pagina che risponde con un errore, oppure a un record che non ha più un indirizzo.',
            'why' => 'Un menu è su ogni pagina: una voce rotta è un link rotto ovunque, e la prima cosa su cui clicca un visitatore.',
            'fix' => 'Fai puntare la voce a una pagina esistente, oppure rimuovila.',
        ],
        'redirect' => [
            'title' => 'Voci di menu che portano a un reindirizzamento',
            'found' => 'Una voce di menu porta a un indirizzo che reindirizza altrove.',
            'why' => 'Ogni clic costa un viaggio in più, su ogni pagina in cui il menu è stampato.',
            'fix' => 'Fai puntare la voce all’indirizzo in cui finisce.',
        ],
    ],
];
