<?php

declare(strict_types=1);

// Why a method is not offered (spec §7.2): for the manager and the agent, never the buyer.
return [
    'nothing_to_pay' => 'Nothing to pay',
    'type_missing' => 'The type :type is not installed',
    'currency' => 'The method does not take :currency',
    'amount_below_min' => 'The amount is below the method\'s minimum',
    'amount_above_max' => 'The amount is above the method\'s maximum',
    'delivery_pending' => 'Waits for the buyer to choose a delivery',
    'delivery_rule' => 'Not offered for the chosen delivery',
    'no_credentials' => 'The gateway\'s keys are not set',
    'gateway_missing' => 'The gateway :gateway is not installed',
];
