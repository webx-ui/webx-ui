<?php

declare(strict_types=1);

return [
    'public' => [
        'accepted' => [
            'name' => 'Aceito',
            'description' => 'Recebemos o seu pedido e entraremos em contato.',
        ],
        'in_progress' => [
            'name' => 'Em andamento',
            'description' => 'Estamos preparando o seu pedido.',
        ],
        'shipped' => [
            'name' => 'Enviado',
            'description' => 'O seu pedido está a caminho.',
        ],
        'completed' => [
            'name' => 'Concluído',
            'description' => 'O pedido está com você. Obrigado!',
        ],
        'cancelled' => [
            'name' => 'Cancelado',
            'description' => 'O pedido foi cancelado.',
        ],
    ],
    'internal' => [
        'new' => 'Novo',
        'awaiting_payment' => 'Aguardando pagamento',
        'taken' => 'Em atendimento',
        'awaiting_call' => 'Aguardando ligação',
        'packing' => 'Em separação',
        'handed_to_delivery' => 'Entregue à transportadora',
        'completed' => 'Concluído',
        'cancelled' => 'Cancelado',
    ],
];
