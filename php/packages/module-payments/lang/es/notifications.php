<?php

declare(strict_types=1);

// The notices of payments (spec §11.5): the values it adds to the order's events and the manager's
// `payments.problem` with its default template; the site's settings tab that chooses its channels.
return [
    'problem' => 'Problema de pago',
    'vars' => [
        'payment_url' => 'Enlace para pagar el pedido',
        'payment_instructions' => 'Datos bancarios para el pago',
        'payment_method' => 'Forma de pago',
        'number' => 'Número de pedido',
        'order_url' => 'Pedido en el panel',
        'problem' => 'Qué ha pasado',
        'amount' => 'Importe',
        'method' => 'Forma de pago',
        'gateway_ref' => 'ID del pago en la pasarela',
        'message' => 'Detalles',
    ],
    'sample' => [
        'subject' => 'Problema de pago: pedido {{number}}',
        'template' => "{{problem}} — pedido {{number}}, {{amount}}, {{method}}.\n{{message}}\nID del pago: {{gateway_ref}}\n{{order_url}}",
    ],
    'settings' => [
        'tab' => 'Pagos',
        'card' => 'Notificaciones',
        'help' => 'A quién avisar si el dinero llega donde no debe: pago doble, importe incorrecto, dinero de un pedido cancelado, una retención a punto de vencer, un cobro rechazado.',
    ],
];
