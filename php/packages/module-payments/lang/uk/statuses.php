<?php

declare(strict_types=1);

// Statuses of attempts, their problems and where they started (spec §8.1, §9.4, §17).
return [
    'statuses' => [
        'created' => 'Створена',
        'pending' => 'В обробці',
        'authorized' => 'Заблокована',
        'paid' => 'Оплачена',
        'failed' => 'Не пройшла',
        'cancelled' => 'Скасована',
        'expired' => 'Минула',
    ],
    'problems' => [
        'duplicate' => 'Оплачено двічі',
        'amount_mismatch' => 'Не та сума',
        'order_cancelled' => 'Замовлення скасовано',
        'hold_expiring' => 'Холд спливає',
        'capture_failed' => 'Списання не пройшло',
    ],
    'sources' => [
        'checkout' => 'Оформлення',
        'pay-page' => 'Сторінка оплати',
        'poll' => 'Фонова перевірка',
        'panel' => 'Панель',
        'mcp' => 'Агент',
        'notify' => 'Сповіщення',
        'return' => 'Повернення',
        'cli' => 'Консоль',
        'api' => 'API',
        'code' => 'Код',
    ],
];
