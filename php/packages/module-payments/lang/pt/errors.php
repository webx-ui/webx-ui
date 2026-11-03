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
        'paid' => 'A encomenda já está paga',
        'held' => 'O dinheiro da encomenda está cativo: a loja irá cobrá-lo',
    ],
    'hold' => [
        'not_held' => 'Este pagamento não tem dinheiro cativo',
        'no_hold' => 'Este método de pagamento não pode cativar dinheiro',
        'amount' => 'O valor deve ser maior que zero',
        'too_much' => 'Mais do que está cativo',
        'partial' => 'Este gateway só cobra o valor cativo completo',
        'busy' => 'O pagamento está a ser atualizado, tente novamente dentro de um minuto',
    ],
    'problem' => [
        'none' => 'O pagamento não tem nenhum problema',
        'hold' => 'Uma cativação resolve-se cobrando-a ou libertando-a',
    ],
];
