<?php

declare(strict_types=1);

return [
    'orders' => 'Ordini',
    'description' => 'Gli ordini fatti sul sito: cosa è stato comprato, da chi e a che punto è ciascuno.',
    'empty' => 'Ancora nessun ordine.',
    'empty-help' => 'Un ordine compare qui non appena viene fatto sul sito.',
    'order' => 'Ordine',
    'fields' => [
        'customer_first_name' => 'Nome',
        'customer_last_name' => 'Cognome',
        'customer_phone' => 'Telefono',
        'customer_email' => 'E-mail',
        'recipient' => 'Destinatario',
        'comment' => 'Commento dell\'acquirente',
        'manager_note' => 'Nota del gestore',
    ],
    'milestones' => [
        'placed' => 'Effettuato',
        'awaiting_payment' => 'In attesa di pagamento',
        'paid' => 'Pagato',
        'received' => 'Ricevuto',
        'cancelled' => 'Annullato',
        'refunded' => 'Rimborsato',
    ],
    'colors' => [
        'neutral' => 'Neutro',
        'info' => 'Info',
        'success' => 'Successo',
        'warning' => 'Avviso',
        'danger' => 'Pericolo',
        'accent' => 'Accento',
    ],
];
