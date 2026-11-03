<?php

declare(strict_types=1);

// Why a method is not offered (spec §7.2): for the manager and the agent, never the buyer.
return [
    'nothing_to_pay' => 'Нічого оплачувати',
    'type_missing' => 'Тип :type не встановлено',
    'currency' => 'Спосіб не приймає :currency',
    'amount_below_min' => 'Сума менша за мінімальну для способу',
    'amount_above_max' => 'Сума більша за максимальну для способу',
    'delivery_pending' => 'Чекає вибору доставки',
    'delivery_rule' => 'Недоступний для обраної доставки',
    'no_credentials' => 'Ключі шлюзу не задано',
    'gateway_missing' => 'Шлюз :gateway не встановлено',
];
