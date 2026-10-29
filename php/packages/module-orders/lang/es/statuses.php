<?php

declare(strict_types=1);

return [
    'public' => [
        'accepted' => [
            'name' => 'Aceptado',
            'description' => 'Tenemos tu pedido y te contactaremos.',
        ],
        'in_progress' => [
            'name' => 'En curso',
            'description' => 'Estamos preparando tu pedido.',
        ],
        'shipped' => [
            'name' => 'Enviado',
            'description' => 'Tu pedido está en camino.',
        ],
        'completed' => [
            'name' => 'Completado',
            'description' => 'El pedido está contigo. ¡Gracias!',
        ],
        'cancelled' => [
            'name' => 'Cancelado',
            'description' => 'El pedido fue cancelado.',
        ],
    ],
    'internal' => [
        'new' => 'Nuevo',
        'awaiting_payment' => 'Pendiente de pago',
        'taken' => 'En gestión',
        'awaiting_call' => 'Espera una llamada',
        'packing' => 'En preparación',
        'handed_to_delivery' => 'Entregado al transportista',
        'completed' => 'Completado',
        'cancelled' => 'Cancelado',
    ],
];
