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
        'paid' => 'El pedido ya está pagado',
        'held' => 'El dinero del pedido está retenido: la tienda lo cobrará',
    ],
    'hold' => [
        'not_held' => 'Este pago no tiene dinero retenido',
        'no_hold' => 'Este método de pago no puede retener dinero',
        'amount' => 'El importe debe ser mayor que cero',
        'too_much' => 'Más de lo retenido',
        'partial' => 'Esta pasarela solo cobra el importe retenido completo',
        'busy' => 'El pago se está actualizando, inténtelo de nuevo en un minuto',
    ],
    'problem' => [
        'none' => 'El pago no tiene ningún problema',
        'hold' => 'Una retención se resuelve cobrándola o liberándola',
    ],
];
