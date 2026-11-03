<?php

declare(strict_types=1);

// Refusals of the service, the API and the agent's tools (spec §17).
return [
    'mark' => [
        'paid' => 'Заказ уже оплачен',
        'cancelled' => 'Заказ отменён',
    ],
    'begin' => [
        'cancelled' => 'Заказ отменён',
        'nothing_due' => 'По заказу нечего платить',
        'not_online' => 'Способ не онлайн',
    ],
];
