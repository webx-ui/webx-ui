<?php

declare(strict_types=1);

// What the buyer reads (spec §10, §12, §17): the payment step, the bank details, the order's line.
return [
    'step' => [
        'methods' => 'Metodo di pagamento',
        'save' => 'Salva',
        'delivery_first' => 'Scegli prima la consegna: da essa dipendono i metodi di pagamento',
        'method_dropped' => 'Il metodo di pagamento scelto non è disponibile per questa consegna. Scegline un altro.',
    ],
    'errors' => [
        'choose_method' => 'Scegli un metodo di pagamento',
        'method_gone' => 'Il metodo di pagamento non è più disponibile, scegline un altro',
    ],
    'instructions' => [
        'title' => 'Coordinate bancarie per il pagamento',
        'recipient' => 'Beneficiario',
        'account' => 'Conto',
        'purpose' => 'Causale',
        'note' => 'Termine',
    ],
    'status' => [
        'paid' => 'Pagato',
        'awaiting' => 'In attesa di pagamento',
        'pay' => 'Paga',
    ],
];
