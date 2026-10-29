<?php

declare(strict_types=1);

return [
    'marks' => [
        'paid' => 'Pagato',
        'received' => 'Ricevuto',
        'cancelled' => 'Annullato',
        'refunded' => 'Rimborsato :amount',
    ],
    'blocks' => [
        'delivery' => 'Consegna',
        'payment' => 'Pagamento',
    ],
    'order' => [
        'title' => 'Ordine :number',
        'placed' => 'Effettuato il :date',
        'scale' => 'A che punto è l’ordine',
        'awaiting_payment' => 'L’ordine attende il pagamento.',
        'items' => 'Articoli',
        'subtotal' => 'Articoli',
        'total' => 'Totale',
        'tenders' => 'Pagato con saldo e carte regalo',
        'payable' => 'Da pagare',
        'contacts' => 'Contatti',
        'customer' => 'Acquirente',
        'recipient' => 'Destinatario',
        'comment' => 'Commento',
    ],
    'account' => [
        'menu' => 'I miei ordini',
        'title' => 'I miei ordini',
        'empty' => 'Ancora nessun ordine. Qui compariranno gli ordini fatti dopo l’accesso.',
        'back' => '← Tutti gli ordini',
        'lines' => 'Articoli: :count',
        'pages' => 'Pagine',
        'newer' => '← Più recenti',
        'older' => 'Meno recenti →',
        'deletion' => 'Gli ordini in corso (:numbers) saranno evasi, ma non li vedrà più nel suo account.',
    ],
];
