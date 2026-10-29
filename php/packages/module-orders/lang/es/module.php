<?php

declare(strict_types=1);

return [
    'orders' => 'Pedidos',
    'description' => 'Los pedidos hechos en el sitio: qué se compró, quién y en qué punto está cada uno.',
    'empty' => 'Todavía no hay pedidos.',
    'empty-help' => 'Un pedido aparece aquí en cuanto se hace en el sitio.',
    'order' => 'Pedido',
    'fields' => [
        'customer_first_name' => 'Nombre',
        'customer_last_name' => 'Apellido',
        'customer_phone' => 'Teléfono',
        'customer_email' => 'Correo electrónico',
        'recipient' => 'Destinatario',
        'comment' => 'Comentario del comprador',
        'manager_note' => 'Nota del gestor',
    ],
    'milestones' => [
        'placed' => 'Realizado',
        'awaiting_payment' => 'Pendiente de pago',
        'paid' => 'Pagado',
        'received' => 'Recibido',
        'cancelled' => 'Cancelado',
        'refunded' => 'Reembolsado',
    ],
    'colors' => [
        'neutral' => 'Neutro',
        'info' => 'Información',
        'success' => 'Éxito',
        'warning' => 'Advertencia',
        'danger' => 'Peligro',
        'accent' => 'Acento',
    ],
];
