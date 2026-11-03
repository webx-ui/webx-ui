<?php

declare(strict_types=1);

// Why a method is not offered (spec §7.2): for the manager and the agent, never the buyer.
return [
    'nothing_to_pay' => 'Nada a pagar',
    'type_missing' => 'O tipo :type não está instalado',
    'currency' => 'A forma não aceita :currency',
    'amount_below_min' => 'O valor é inferior ao mínimo',
    'amount_above_max' => 'O valor excede o máximo',
    'delivery_pending' => 'Aguarda a escolha da entrega',
    'delivery_rule' => 'Indisponível para a entrega escolhida',
    'no_credentials' => 'As chaves do gateway não estão definidas',
    'gateway_missing' => 'O gateway :gateway não está instalado',
];
