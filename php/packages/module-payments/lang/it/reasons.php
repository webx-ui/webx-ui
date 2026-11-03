<?php

declare(strict_types=1);

// Why a method is not offered (spec §7.2): for the manager and the agent, never the buyer.
return [
    'nothing_to_pay' => 'Niente da pagare',
    'type_missing' => 'Il tipo :type non è installato',
    'currency' => 'Il metodo non accetta :currency',
    'amount_below_min' => 'L\'importo è inferiore al minimo',
    'amount_above_max' => 'L\'importo supera il massimo',
    'delivery_pending' => 'Attende la scelta della consegna',
    'delivery_rule' => 'Non disponibile per la consegna scelta',
    'no_credentials' => 'Le chiavi del gateway non sono impostate',
    'gateway_missing' => 'Il gateway :gateway non è installato',
];
