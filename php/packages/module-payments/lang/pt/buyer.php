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
    'pay' => [
        'title' => 'Pagamento do pedido :number',
        'description' => 'Pedido :number',
        'due' => 'A pagar: :amount',
        'paid' => 'O pedido :number está pago',
        'cancelled' => 'O pedido :number está cancelado',
        'checking' => 'Estamos a verificar o pagamento. Normalmente demora até um minuto.',
        'failed' => 'O pagamento não foi concluído',
        'unavailable' => 'Não foi possível abrir o pagamento. Tente novamente ou escolha outro método.',
        'on_receipt' => 'O pedido é pago na entrega',
        'order_link' => 'Ir para o pedido',
        'redirecting' => 'A levá-lo para a página de pagamento…',
        'go' => 'Ir para o pagamento',
    ],
];
