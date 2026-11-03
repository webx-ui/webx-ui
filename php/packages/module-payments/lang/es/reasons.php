<?php

declare(strict_types=1);

// Why a method is not offered (spec §7.2): for the manager and the agent, never the buyer.
return [
    'nothing_to_pay' => 'Nada que pagar',
    'type_missing' => 'El tipo :type no está instalado',
    'currency' => 'El método no acepta :currency',
    'amount_below_min' => 'El importe es inferior al mínimo',
    'amount_above_max' => 'El importe supera el máximo',
    'delivery_pending' => 'Espera la elección del envío',
    'delivery_rule' => 'No disponible para el envío elegido',
    'no_credentials' => 'Las claves de la pasarela no están configuradas',
    'gateway_missing' => 'La pasarela :gateway no está instalada',
];
