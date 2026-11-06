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
        'checking_long' => 'O banco ainda não confirmou o pagamento. Se o dinheiro foi cobrado, a encomenda ficará marcada como paga sozinha e enviaremos um e-mail. Pode fechar esta página.',
        'check_again' => 'Verificar novamente',
        'held' => 'Pagamento aceite. O valor está reservado no seu cartão e será cobrado pela loja.',
        'received' => 'Pagamento recebido',
        'accepted' => 'Pagamento aceite',
        'failed' => 'O pagamento não foi concluído',
        'unavailable' => 'Não foi possível abrir o pagamento. Tente novamente ou escolha outro método.',
        'on_receipt' => 'O pedido é pago na entrega',
        'order_link' => 'Ir para o pedido',
        'redirecting' => 'A levá-lo para a página de pagamento…',
        'go' => 'Ir para o pagamento',
        'top_up' => 'Falta pagar: :amount',
        'top_up_button' => 'Pagar a diferença',
        'online_now' => 'Pagar online agora',
        'wait' => 'Se já pagou noutro separador, aguarde um minuto antes de pagar novamente.',
        'declined' => 'O pagamento não foi concluído: o banco recusou o cartão',
        'unfinished' => 'Pagamento não concluído',
        'pay_again' => 'Pagar novamente',
        'other_method' => 'Escolher outro método',
        'continue' => 'Continuar',
    ],
];
