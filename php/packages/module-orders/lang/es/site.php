<?php

declare(strict_types=1);

return [
    'marks' => [
        'paid' => 'Pagado',
        'received' => 'Recibido',
        'cancelled' => 'Cancelado',
        'refunded' => 'Reembolsado :amount',
    ],
    'blocks' => [
        'delivery' => 'Entrega',
        'payment' => 'Pago',
    ],
    'order' => [
        'title' => 'Pedido :number',
        'placed' => 'Hecho el :date',
        'scale' => 'Dónde está el pedido',
        'awaiting_payment' => 'El pedido espera el pago.',
        'items' => 'Artículos',
        'subtotal' => 'Artículos',
        'total' => 'Total',
        'tenders' => 'Pagado con saldo y tarjetas regalo',
        'payable' => 'A pagar',
        'contacts' => 'Contactos',
        'customer' => 'Comprador',
        'recipient' => 'Destinatario',
        'comment' => 'Comentario',
    ],
    'account' => [
        'menu' => 'Mis pedidos',
        'title' => 'Mis pedidos',
        'empty' => 'Todavía no hay pedidos. Aquí aparecerán los pedidos que haga con la sesión iniciada.',
        'back' => '← Todos los pedidos',
        'lines' => 'Artículos: :count',
        'pages' => 'Páginas',
        'newer' => '← Más recientes',
        'older' => 'Más antiguos →',
        'deletion' => 'Los pedidos en curso (:numbers) se completarán, pero ya no los verá en su cuenta.',
    ],
];
