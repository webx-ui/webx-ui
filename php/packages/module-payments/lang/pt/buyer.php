<?php

declare(strict_types=1);

// What the buyer reads (spec §10, §12, §17): the payment step, the bank details, the order's line.
return [
    'step' => [
        'methods' => 'Forma de pagamento',
        'save' => 'Guardar',
        'delivery_first' => 'Escolha primeiro a entrega — as formas de pagamento dependem dela',
        'method_dropped' => 'A forma de pagamento escolhida não está disponível para esta entrega. Escolha outra.',
    ],
    'errors' => [
        'choose_method' => 'Escolha uma forma de pagamento',
        'method_gone' => 'A forma de pagamento já não está disponível, escolha outra',
    ],
    'instructions' => [
        'title' => 'Dados bancários para pagamento',
        'recipient' => 'Beneficiário',
        'account' => 'Conta',
        'purpose' => 'Descrição do pagamento',
        'note' => 'Prazo',
    ],
    'status' => [
        'paid' => 'Pago',
        'awaiting' => 'A aguardar pagamento',
        'pay' => 'Pagar',
    ],
];
