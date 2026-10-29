<?php

declare(strict_types=1);

return [
    'public' => [
        'accepted' => [
            'name' => 'Accettato',
            'description' => 'Abbiamo il tuo ordine e ti contatteremo.',
        ],
        'in_progress' => [
            'name' => 'In lavorazione',
            'description' => 'Stiamo preparando il tuo ordine.',
        ],
        'shipped' => [
            'name' => 'Spedito',
            'description' => 'Il tuo ordine è in viaggio.',
        ],
        'completed' => [
            'name' => 'Completato',
            'description' => 'L’ordine è da te. Grazie!',
        ],
        'cancelled' => [
            'name' => 'Annullato',
            'description' => 'L’ordine è stato annullato.',
        ],
    ],
    'internal' => [
        'new' => 'Nuovo',
        'awaiting_payment' => 'In attesa di pagamento',
        'taken' => 'Preso in carico',
        'awaiting_call' => 'In attesa di chiamata',
        'packing' => 'In preparazione',
        'handed_to_delivery' => 'Affidato al corriere',
        'completed' => 'Completato',
        'cancelled' => 'Annullato',
    ],
];
