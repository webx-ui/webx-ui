<?php

declare(strict_types=1);

// Refusals of the service, the API and the agent's tools (spec §17).
return [
    'mark' => [
        'paid' => 'A encomenda já está paga',
        'cancelled' => 'A encomenda está cancelada',
    ],
    'begin' => [
        'cancelled' => 'O pedido está cancelado',
        'nothing_due' => 'Não há nada a pagar no pedido',
        'not_online' => 'O método não é de pagamento online',
    ],
];
