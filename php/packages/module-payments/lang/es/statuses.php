<?php

declare(strict_types=1);

// Statuses of attempts, their problems and where they started (spec §8.1, §9.4, §17).
return [
    'statuses' => [
        'created' => 'Creado',
        'pending' => 'En curso',
        'authorized' => 'Retenido',
        'paid' => 'Pagado',
        'failed' => 'Fallido',
        'cancelled' => 'Cancelado',
        'expired' => 'Caducado',
    ],
    'problems' => [
        'duplicate' => 'Pagado dos veces',
        'amount_mismatch' => 'Importe incorrecto',
        'order_cancelled' => 'Pedido cancelado',
        'hold_expiring' => 'La retención caduca',
        'capture_failed' => 'El cobro falló',
    ],
    'sources' => [
        'checkout' => 'Compra',
        'pay-page' => 'Página de pago',
        'poll' => 'Comprobación en segundo plano',
        'panel' => 'Panel',
        'mcp' => 'Agente',
        'notify' => 'Notificación',
        'return' => 'Retorno',
        'cli' => 'Consola',
        'api' => 'API',
        'code' => 'Código',
    ],
];
