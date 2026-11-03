<?php

declare(strict_types=1);

// Statuses of attempts, their problems and where they started (spec §8.1, §9.4, §17).
return [
    'statuses' => [
        'created' => 'Criada',
        'pending' => 'Em andamento',
        'authorized' => 'Reservada',
        'paid' => 'Paga',
        'failed' => 'Falhou',
        'cancelled' => 'Cancelada',
        'expired' => 'Expirada',
    ],
    'problems' => [
        'duplicate' => 'Paga duas vezes',
        'amount_mismatch' => 'Valor errado',
        'order_cancelled' => 'Pedido cancelado',
        'hold_expiring' => 'A reserva expira',
        'capture_failed' => 'A captura falhou',
    ],
    'sources' => [
        'checkout' => 'Checkout',
        'pay-page' => 'Página de pagamento',
        'poll' => 'Verificação em segundo plano',
        'panel' => 'Painel',
        'mcp' => 'Agente',
        'notify' => 'Notificação',
        'return' => 'Retorno',
        'cli' => 'Console',
        'api' => 'API',
        'code' => 'Código',
    ],
];
