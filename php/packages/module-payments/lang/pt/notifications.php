<?php

declare(strict_types=1);

// The notices of payments (spec §11.5): the values it adds to the order's events and the manager's
// `payments.problem` with its default template; the site's settings tab that chooses its channels.
return [
    'problem' => 'Problema de pagamento',
    'vars' => [
        'payment_url' => 'Link para pagar o pedido',
        'payment_instructions' => 'Dados bancários para o pagamento',
        'payment_method' => 'Forma de pagamento',
        'number' => 'Número do pedido',
        'order_url' => 'Pedido no painel',
        'problem' => 'O que aconteceu',
        'amount' => 'Valor',
        'method' => 'Forma de pagamento',
        'gateway_ref' => 'ID do pagamento no gateway',
        'message' => 'Detalhes',
    ],
    'sample' => [
        'subject' => 'Problema de pagamento: pedido {{number}}',
        'template' => "{{problem}} — pedido {{number}}, {{amount}}, {{method}}.\n{{message}}\nID do pagamento: {{gateway_ref}}\n{{order_url}}",
    ],
    'settings' => [
        'tab' => 'Pagamentos',
        'card' => 'Notificações',
        'help' => 'Quem avisar quando o dinheiro chega onde não devia: pago duas vezes, valor errado, dinheiro de um pedido cancelado, uma pré-autorização a expirar, uma captura recusada.',
    ],
];
