<?php

declare(strict_types=1);

return [
    'marks' => [
        'paid' => 'Paga',
        'received' => 'Recebida',
        'cancelled' => 'Cancelada',
        'refunded' => 'Reembolsado :amount',
    ],
    'blocks' => [
        'delivery' => 'Entrega',
        'payment' => 'Pagamento',
    ],
    'order' => [
        'title' => 'Encomenda :number',
        'placed' => 'Feita em :date',
        'scale' => 'Onde está a encomenda',
        'awaiting_payment' => 'A encomenda aguarda pagamento.',
        'items' => 'Artigos',
        'subtotal' => 'Artigos',
        'total' => 'Total',
        'tenders' => 'Pago com saldo e cartões-presente',
        'payable' => 'A pagar',
        'contacts' => 'Contactos',
        'customer' => 'Comprador',
        'recipient' => 'Destinatário',
        'comment' => 'Comentário',
    ],
    'account' => [
        'menu' => 'As minhas encomendas',
        'title' => 'As minhas encomendas',
        'empty' => 'Ainda não há encomendas. Aqui aparecerão as encomendas feitas com sessão iniciada.',
        'back' => '← Todas as encomendas',
        'lines' => 'Artigos: :count',
        'pages' => 'Páginas',
        'newer' => '← Mais recentes',
        'older' => 'Mais antigas →',
        'deletion' => 'As encomendas em curso (:numbers) serão concluídas, mas deixará de as ver na sua conta.',
    ],
];
