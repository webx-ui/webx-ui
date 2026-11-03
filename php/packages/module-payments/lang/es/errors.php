<?php

declare(strict_types=1);

// Refusals of the service, the API and the agent's tools (spec §17).
return [
    'mark' => [
        'paid' => 'El pedido ya está pagado',
        'cancelled' => 'El pedido está cancelado',
    ],
    'begin' => [
        'cancelled' => 'El pedido está cancelado',
        'nothing_due' => 'El pedido no tiene nada pendiente de pago',
        'not_online' => 'El método no es de pago en línea',
    ],
];
