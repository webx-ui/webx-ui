<?php

declare(strict_types=1);

// Why a method is not offered (spec §7.2): for the manager and the agent, never the buyer.
return [
    'nothing_to_pay' => 'Нечего оплачивать',
    'type_missing' => 'Тип :type не установлен',
    'currency' => 'Способ не принимает :currency',
    'amount_below_min' => 'Сумма меньше минимальной для способа',
    'amount_above_max' => 'Сумма больше максимальной для способа',
    'delivery_pending' => 'Ждёт выбора доставки',
    'delivery_rule' => 'Недоступен при выбранной доставке',
    'no_credentials' => 'Ключи шлюза не заданы',
    'gateway_missing' => 'Шлюз :gateway не установлен',
];
